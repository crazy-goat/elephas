#!/usr/bin/env bash
#
# Build the tb_client native shared library for the current host platform.
#
# Output: resources/lib/<platform>/libtb_client.{so,dylib}
# where <platform> matches the directory names expected by
# CrazyGoat\Elephas\Backend\NativeClient::detectLibraryPath():
#   - linux-amd64, linux-arm64
#   - macos-amd64, macos-arm64
#
# Environment variables (all optional):
#   TB_VERSION      TigerBeetle release tag to build (default: 0.17.4)
#   ZIG_VERSION     Zig toolchain version (default: 0.14.1)
#   OUTPUT_DIR      Where to install the built library
#                   (default: <repo-root>/resources/lib)
#   TB_TARGET       Override the upstream clients/c sub-directory used to
#                   locate the built library (default: auto-detected)
#   ZIG_BIN         Path to a zig executable (skips auto-install)
#   SKIP_ZIG_INSTALL=1  Fail if zig is not on PATH instead of downloading
#   SKIP_CLONE=1        Reuse a previously cloned TB_SRC_DIR
#   TB_SRC_DIR      Path to an existing tigerbeetle source tree
#
# Exit codes:
#   0  build succeeded
#   1  invalid arguments / unsupported host
#   2  prerequisite missing (git, tar, xz)
#   3  zig download / extraction failed
#   4  clone failed
#   5  zig build failed
#   6  built library not found at expected path

set -euo pipefail

readonly SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
readonly REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

TB_VERSION="${TB_VERSION:-0.17.4}"
ZIG_VERSION="${ZIG_VERSION:-0.14.1}"
OUTPUT_DIR="${OUTPUT_DIR:-}"
TB_TARGET="${TB_TARGET:-}"
ZIG_BIN="${ZIG_BIN:-}"
TB_SRC_DIR="${TB_SRC_DIR:-}"
SKIP_ZIG_INSTALL="${SKIP_ZIG_INSTALL:-}"
SKIP_CLONE="${SKIP_CLONE:-}"

TB_REPO="https://github.com/tigerbeetle/tigerbeetle.git"
ZIG_BASE_URL="https://ziglang.org/download"

usage() {
    cat <<USAGE
Usage: $SCRIPT_NAME [options]

Build the TigerBeetle tb_client native shared library for the current host.

Options:
  --check         Print the detected platform/target and exit (no build).
  --clean         Remove the local build and zig cache directories, then exit.
  --help          Show this help and exit.

Environment variables (override defaults):
  TB_VERSION      TigerBeetle tag to build       (default: $TB_VERSION)
  ZIG_VERSION     Zig toolchain version          (default: $ZIG_VERSION)
  OUTPUT_DIR      Install directory               (default: <repo>/resources/lib)
  TB_TARGET       Override detected platform sub-dir
                   (default: auto-detected)
  ZIG_BIN         Path to a zig executable       (default: auto-installed)
  TB_SRC_DIR      Reuse an existing tigerbeetle source checkout.
  SKIP_ZIG_INSTALL=1   Fail if zig is missing instead of downloading.
  SKIP_CLONE=1         Reuse TB_SRC_DIR without re-cloning.

USAGE
}

log() {
    printf '[%s] %s\n' "$SCRIPT_NAME" "$*" >&2
}

die() {
    local code=1
    if [ "${1:-}" = "--code" ]; then
        code="$2"
        shift 2
    fi
    log "ERROR: $*"
    exit "$code"
}

require_cmd() {
    local cmd="$1"
    command -v "$cmd" >/dev/null 2>&1 || die --code 2 "required command not found: $cmd"
}

detect_host() {
    local os
    local arch
    os="$(uname -s | tr '[:upper:]' '[:lower:]')"
    arch="$(uname -m)"

    case "$os/$arch" in
        linux/x86_64|linux/amd64)
            printf 'linux-amd64\n'
            ;;
        linux/aarch64|linux/arm64)
            printf 'linux-arm64\n'
            ;;
        darwin/x86_64|darwin/amd64)
            printf 'macos-amd64\n'
            ;;
        darwin/arm64|darwin/aarch64)
            printf 'macos-arm64\n'
            ;;
        *)
            die --code 1 "unsupported host platform: $os/$arch"
            ;;
    esac
}

target_triple_for() {
    local platform="$1"
    case "$platform" in
        linux-amd64) printf 'x86_64-linux-gnu.2.27\n' ;;
        linux-arm64) printf 'aarch64-linux-gnu.2.27\n' ;;
        macos-amd64) printf 'x86_64-macos\n' ;;
        macos-arm64) printf 'aarch64-macos\n' ;;
        *) die --code 1 "no target triple mapping for platform: $platform" ;;
    esac
}

lib_filename_for() {
    local platform="$1"
    case "$platform" in
        *-amd64|*-arm64)
            case "$platform" in
                linux-*) printf 'libtb_client.so\n' ;;
                macos-*) printf 'libtb_client.dylib\n' ;;
            esac
            ;;
        *) die --code 1 "no library filename for platform: $platform" ;;
    esac
}

# Compare two dotted version strings; returns 0 if $1 >= $2, 1 otherwise.
version_gte() {
    local IFS=.
    local -a v1=($1) v2=($2)
    for ((i=0; i<${#v1[@]}; i++)); do
        if ((10#${v1[i]:-0} > 10#${v2[i]:-0})); then return 0; fi
        if ((10#${v1[i]:-0} < 10#${v2[i]:-0})); then return 1; fi
    done
    return 0
}

zig_archive_name() {
    local host_os="$1"
    local host_arch="$2"
    local os_part arch_part

    case "$host_os" in
        linux)  os_part=linux  ;;
        darwin) os_part=macos  ;;
        *) die --code 1 "no zig archive for host: $host_os/$host_arch" ;;
    esac

    case "$host_arch" in
        x86_64|amd64)  arch_part=x86_64  ;;
        aarch64|arm64) arch_part=aarch64 ;;
        *) die --code 1 "no zig archive for host: $host_os/$host_arch" ;;
    esac

    if version_gte "$ZIG_VERSION" "0.14.1"; then
        printf 'zig-%s-%s-%s.tar.xz\n' "$arch_part" "$os_part" "$ZIG_VERSION"
    else
        printf 'zig-%s-%s-%s.tar.xz\n' "$os_part" "$arch_part" "$ZIG_VERSION"
    fi
}

zig_minimal_path() {
    # Trim a long absolute path so the printed status stays readable.
    local p="$1"
    if [ "${#p}" -gt 60 ]; then
        printf '...%s' "${p: -57}"
    else
        printf '%s' "$p"
    fi
}

ensure_zig() {
    if [ -n "$ZIG_BIN" ] && [ -x "$ZIG_BIN" ]; then
        log "using zig from ZIG_BIN: $(zig_minimal_path "$ZIG_BIN")"
        return 0
    fi

    if command -v zig >/dev/null 2>&1; then
        local found
        found="$(command -v zig)"
        # Only trust system zig if its version matches ZIG_VERSION. The
        # build.zig in TigerBeetle compile-errors on version mismatch.
        local actual
        actual="$("$found" version 2>/dev/null | awk '{print $NF}' || true)"
        if [ -n "$actual" ] && [ "$actual" = "$ZIG_VERSION" ]; then
            ZIG_BIN="$found"
            log "using zig from PATH: $(zig_minimal_path "$ZIG_BIN") ($actual)"
            return 0
        fi
        log "system zig ($actual) does not match required $ZIG_VERSION; will download"
    fi

    if [ -n "$SKIP_ZIG_INSTALL" ]; then
        die --code 3 "zig $ZIG_VERSION not found and SKIP_ZIG_INSTALL is set"
    fi

    require_cmd tar
    require_cmd xz
    require_cmd curl

    local host_os host_arch archive cache_dir extract_dir
    host_os="$(uname -s | tr '[:upper:]' '[:lower:]')"
    host_arch="$(uname -m)"
    archive="$(zig_archive_name "$host_os" "$host_arch")"
    cache_dir="${XDG_CACHE_HOME:-$HOME/.cache}/elephas"
    extract_dir="$cache_dir/zig-$ZIG_VERSION"

    if [ -x "$extract_dir/zig" ]; then
        ZIG_BIN="$extract_dir/zig"
        log "using cached zig at $(zig_minimal_path "$ZIG_BIN")"
        return 0
    fi

    mkdir -p "$cache_dir"
    local url="$ZIG_BASE_URL/$ZIG_VERSION/$archive"
    local tmp_tar
    tmp_tar="$(mktemp "$cache_dir/${archive}.XXXX")"
    log "downloading $url"
    if ! curl --fail --location --silent --show-error -o "$tmp_tar" "$url"; then
        rm -f "$tmp_tar"
        die --code 3 "failed to download zig from $url"
    fi

    log "extracting $(basename "$tmp_tar")"
    local tmp_extract
    tmp_extract="$(mktemp -d "$cache_dir/extract.XXXX")"
    if ! tar -xJf "$tmp_tar" -C "$tmp_extract"; then
        rm -rf "$tmp_extract" "$tmp_tar"
        die --code 3 "failed to extract zig archive $archive"
    fi
    rm -f "$tmp_tar"

    # The tarball expands to a directory whose name matches the archive stem
    # (e.g. zig-linux-x86_64-0.14.1). Move it to a stable location.
    local extracted
    extracted="$(find "$tmp_extract" -mindepth 1 -maxdepth 1 -type d -name 'zig-*' | head -n1)"
    if [ -z "$extracted" ]; then
        rm -rf "$tmp_extract"
        die --code 3 "could not locate zig directory inside archive"
    fi
    rm -rf "$extract_dir"
    mv "$extracted" "$extract_dir"
    rm -rf "$tmp_extract"

    if [ ! -x "$extract_dir/zig" ]; then
        die --code 3 "zig binary not found in $extract_dir after extraction"
    fi
    ZIG_BIN="$extract_dir/zig"
    log "installed zig at $(zig_minimal_path "$ZIG_BIN")"
}

ensure_source() {
    if [ -n "$TB_SRC_DIR" ] && [ -f "$TB_SRC_DIR/build.zig" ]; then
        log "using existing source at $TB_SRC_DIR"
        return 0
    fi

    if [ -n "$SKIP_CLONE" ]; then
        die --code 4 "TB_SRC_DIR not set or invalid and SKIP_CLONE is set"
    fi

    require_cmd git

    local cache_dir
    cache_dir="${XDG_CACHE_HOME:-$HOME/.cache}/elephas"
    mkdir -p "$cache_dir"

    if [ -z "$TB_SRC_DIR" ]; then
        TB_SRC_DIR="$cache_dir/tigerbeetle-$TB_VERSION"
    fi

    if [ ! -d "$TB_SRC_DIR/.git" ]; then
        log "cloning tigerbeetle $TB_VERSION into $TB_SRC_DIR"
        rm -rf "$TB_SRC_DIR"
        git clone --depth 1 --branch "$TB_VERSION" "$TB_REPO" "$TB_SRC_DIR" \
            || die --code 4 "git clone failed"
    else
        log "reusing existing checkout at $TB_SRC_DIR"
    fi
}

run_build() {
    local target="$1"
    log "running zig build clients:c -Drelease-safe (target: $target)"
    # The `clients:c` step in TigerBeetle's build.zig builds the dynamic
    # library for all supported platforms and installs them under
    # <src>/src/clients/c/lib/<platform>/libtb_client.{so,dylib}.
    ( cd "$TB_SRC_DIR" && "$ZIG_BIN" build clients:c -Drelease-safe ) \
        || die --code 5 "zig build failed"
}

install_library() {
    local platform="$1"
    local lib_name
    lib_name="$(lib_filename_for "$platform")"
    local sub_dir
    sub_dir="$(target_triple_for "$platform")"
    local out_root
    if [ -n "$OUTPUT_DIR" ]; then
        out_root="$OUTPUT_DIR"
    else
        out_root="$REPO_ROOT/resources/lib"
    fi
    local out_dir="$out_root/$platform"
    local out_path="$out_dir/$lib_name"

    # The upstream build.zig installs libraries at
    # <src>/src/clients/c/lib/<sub_dir>/<lib_name>.
    local built_path="$TB_SRC_DIR/src/clients/c/lib/$sub_dir/$lib_name"
    if [ ! -f "$built_path" ]; then
        die --code 6 "expected built library not found: $built_path"
    fi

    mkdir -p "$out_dir"
    cp "$built_path" "$out_path"
    chmod 0644 "$out_path"
    log "installed library: $out_path"
    printf '%s\n' "$out_path"
}

do_check() {
    local platform triple lib_name out_root out_path
    platform="$(detect_host)"
    triple="$(target_triple_for "$platform")"
    lib_name="$(lib_filename_for "$platform")"
    if [ -n "$OUTPUT_DIR" ]; then
        out_root="$OUTPUT_DIR"
    else
        out_root="$REPO_ROOT/resources/lib"
    fi
    out_path="$out_root/$platform/$lib_name"
    if [ -n "$TB_TARGET" ]; then
        triple="$TB_TARGET"
    fi

    printf 'host_platform=%s\n' "$platform"
    printf 'clients_lib_subdir=%s\n' "$triple"
    printf 'output_path=%s\n' "$out_path"
}

do_clean() {
    local cache_dir="${XDG_CACHE_HOME:-$HOME/.cache}/elephas"
    if [ -d "$cache_dir" ]; then
        log "removing $cache_dir"
        rm -rf "$cache_dir"
    fi
    local out_root
    if [ -n "$OUTPUT_DIR" ]; then
        out_root="$OUTPUT_DIR"
    else
        out_root="$REPO_ROOT/resources/lib"
    fi
    if [ -d "$out_root" ]; then
        log "removing $out_root"
        rm -rf "$out_root"
    fi
}

main() {
    local action="build"
    while [ $# -gt 0 ]; do
        case "$1" in
            --check) action="check" ;;
            --clean) action="clean" ;;
            --help|-h) usage; exit 0 ;;
            --) shift; break ;;
            -*) die --code 1 "unknown option: $1" ;;
            *) die --code 1 "unexpected argument: $1" ;;
        esac
        shift
    done

    case "$action" in
        check)  do_check; exit 0 ;;
        clean)  do_clean; exit 0 ;;
        build)  : ;;
        *) die --code 1 "internal: unknown action $action" ;;
    esac

    require_cmd uname

    local platform triple
    platform="$(detect_host)"
    triple="$(target_triple_for "$platform")"
    if [ -n "$TB_TARGET" ]; then
        triple="$TB_TARGET"
    fi

    log "host platform: $platform"
    log "clients lib:   src/clients/c/lib/$triple/"
    log "tb version:    $TB_VERSION"

    ensure_zig
    ensure_source

    run_build "$triple"
    install_library "$platform"
    log "build complete"
}

main "$@"
