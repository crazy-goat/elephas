#!/usr/bin/env bash
#
# Picks the most impactful GitHub issues to work on next.
#
# Lists open milestones, selects the lowest one (by version), scores its
# open issues, and prints the top N candidates so a human or an LLM can
# make the final pick. Keeps triage cheap: bodies and comment text are
# never fetched, only titles, labels, age and comment counts.
#
# Shared script: the source of truth is standard/pick-issue.sh in crazy-goat/.github;
# repositories carry an identical copy in bin/pick-issue.sh.
#
# Requires: bash 3.2+, the `gh` CLI (installed and authenticated),
# standard coreutils. No PHP, no Python, no standalone jq — all JSON
# projection uses gh's built-in --jq.
#
# Usage: bin/pick-issue.sh [options]
#
# Options:
#   --repo=owner/name   GitHub repository (default: the current repository)
#   --milestone=X       score issues from this milestone instead of the lowest
#   --top=N             how many candidates to show (default: 5, 0 = all)
#   --json              machine-readable output (JSON on stdout)
#   --json=BOOL         as above with an explicit true/false
#   -h, --help          show this help
#
# Exit codes:
#   0  candidates printed
#   1  gh / API error
#   2  usage error (bad option, unknown milestone)
#   3  RELEASE NEEDED: the target milestone has no open issues left —
#      stop the workflow, cut the release, close the milestone, re-run
#
# Release rule: the workflow works milestone-by-milestone, lowest first.
# An empty milestone ends the picking loop — do not silently move to the
# next one. Cut a release for the finished milestone, close it, then the
# next run will pick the next one.
#
# Scoring (additive, all components shown in the breakdown). Labels follow the
# crazy-goat standard (https://github.com/crazy-goat/.github):
#   - type labels (first match wins): type:bug=50, type:security=45,
#     type:feature=20, type:performance=15, type:refactor=10, type:tests=8,
#     type:docs=8
#   - priority labels: priority:critical=60, priority:high=30,
#     priority:medium=12, priority:low=3
#   - meta labels: good first issue=+10, help wanted=+8, question=-5,
#     status:blocked=-100, status:needs-info=-100 (kept visible, ranked last)
#   - title signals: leak=25, crash/segfault/fatal/panic/corrupt=30,
#     security/auth/xss/csrf/injection=20, performance=15, dead code=5
#   - age:            +0.2 per day since creation, capped at 20
#   - comments:       +1 per comment, capped at 5 (demand signal)
#
# Internal wire format: fields are separated by \x1f (unit separator), NOT
# tabs. `read` treats non-whitespace IFS characters strictly, so empty
# fields (e.g. an issue without labels) survive parsing, while tabs would
# collapse. The jq projections gsub() the separator out of every text field.

set -euo pipefail

DEFAULT_REPO=""   # empty = the repository of the current directory (gh repo view)

# NB: bash 3.2 only — no mapfile, no associative arrays, no ${var,,}.
# Title-signal regexes rely on nocasematch instead of inline /i flags.

EXIT_OK=0
EXIT_GH_ERROR=1
EXIT_USAGE=2
EXIT_RELEASE_NEEDED=3

TOP=5
REPO="$DEFAULT_REPO"
MILESTONE=""
JSON_OUT=0

SEP=$'\x1f'   # field separator; safe because projections gsub it away
LSEP=$'\x1e'  # label separator inside the scored/labels display field

shopt -s nocasematch

# --- date portability (GNU date vs BSD date on macOS) ----------------------
if date -d @0 +%s >/dev/null 2>&1; then
    DATE_GNU=1
else
    DATE_GNU=0
fi

# ISO-8601 timestamp (GitHub: 2025-03-04T10:11:43Z) -> unix epoch seconds.
iso_to_epoch() {
    if [ "$DATE_GNU" -eq 1 ]; then
        date -d "$1" +%s 2>/dev/null
    else
        date -j -f '%Y-%m-%dT%H:%M:%SZ' "$1" +%s 2>/dev/null
    fi
}

# JSON-escape a string (backslash, double quote, newline, stray control
# chars). Portable across GNU and BSD sed: newlines are parked on a
# control char first, then rewritten to the literal two-char sequence \n.
json_escape() {
    local soh
    soh="$(printf '\001')"
    printf '%s' "$1" | tr '\n' '\001' | tr "$(printf '\037\036')" '  ' | \
        sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e "s/${soh}/\\\\n/g"
}

# Label membership test. $1 is a NEWLINE-joined label list (pre-split once
# per issue by score_line), $2 the needle. Literal comparison — robust
# against glob characters in label names. Uses a here-string (not a process
# substitution) so it is safe inside long loops on bash 3.2, which leaks
# process-substitution file descriptors until the enclosing loop ends.
has_label() {
    local one
    while IFS= read -r one; do
        [ "$one" = "$2" ] && return 0
    done <<< "$1"
    return 1
}

usage() {
    cat <<EOF
$(basename "$0") — pick top GitHub issues to work on

Usage: $(basename "$0") [options]

Options:
  --repo=owner/name   GitHub repository (default: the current repository)
  --milestone=X       score issues from this milestone (default: lowest open)
  --top=N             how many candidates to show (default: 5, 0 = all)
  --json              machine-readable output (JSON on stdout)
  --json=BOOL         as above, explicit true/false
  -h, --help          show this help

Exit codes:
  0  candidates printed
  1  gh / API error
  2  usage error
  3  RELEASE NEEDED: target milestone has no open issues left —
     cut the release, close the milestone, re-run
EOF
}

parse_args() {
    while [ $# -gt 0 ]; do
        local arg="$1"
        local name="" value=""

        case "$arg" in
            -h | --help)
                usage
                exit "$EXIT_OK"
                ;;
            --repo=*) name="repo"; value="${arg#*=}" ;;
            --repo) name="repo" ;;
            --milestone=*) name="milestone"; value="${arg#*=}" ;;
            --milestone) name="milestone" ;;
            --top=*) name="top"; value="${arg#*=}" ;;
            --top) name="top" ;;
            --json) JSON_OUT=1 ;;
            --json=*)
                case "${arg#*=}" in
                    true | 1 | on | yes) JSON_OUT=1 ;;
                    false | 0 | off | no) JSON_OUT=0 ;;
                    *)
                        echo "Invalid value for --json: ${arg#*=} (expected true/false)" >&2
                        exit "$EXIT_USAGE"
                        ;;
                esac
                ;;
            *)
                echo "Unknown argument: $arg (see --help)" >&2
                exit "$EXIT_USAGE"
                ;;
        esac

        if [ -n "$name" ]; then
            # Valued options accept both --top=5 and --top 5 forms.
            if [ -z "$value" ]; then
                shift
                if [ $# -eq 0 ]; then
                    echo "Option --$name requires a value." >&2
                    exit "$EXIT_USAGE"
                fi
                value="$1"
            fi

            case "$name" in
                repo)
                    REPO="$value"
                    ;;
                milestone)
                    MILESTONE="$value"
                    ;;
                top)
                    case "$value" in
                        '' | *[!0-9]*)
                            echo "Invalid value for --top: $value (expected a non-negative integer)" >&2
                            exit "$EXIT_USAGE"
                            ;;
                    esac
                    TOP="$value"
                    ;;
            esac
        fi

        shift
    done
}

# Score one issue (fields from a \x1f-separated line) and print:
#   score\x1fnumber\x1ftitle\x1flabels_field\x1frationale\x1fage_days\x1fcomments
score_line() {
    local number="$1" title="$2" labels_field="$3" created_at="$4" comments="$5"
    local score=0
    local breakdown=""
    local entry tlabel tweight

    # Split the \x1f-joined labels into a newline-joined list — once, not
    # per lookup (parameter expansion, no subprocesses).
    local labels_list
    labels_list="${labels_field//$SEP/$'\n'}"

    # Type labels: first (most valuable) match wins.
    for entry in "type:bug:50" "type:security:45" "type:feature:20" \
        "type:performance:15" "type:refactor:10" "type:tests:8" "type:docs:8"; do
        tlabel="${entry%:*}"
        tweight="${entry##*:}"
        if has_label "$labels_list" "$tlabel"; then
            score=$((score + tweight))
            breakdown="$breakdown $tlabel +$tweight"
            break
        fi
    done
    for entry in "priority:critical:60" "priority:high:30" \
        "priority:medium:12" "priority:low:3"; do
        tlabel="${entry%:*}"
        tweight="${entry##*:}"
        if has_label "$labels_list" "$tlabel"; then
            score=$((score + tweight))
            breakdown="$breakdown $tlabel +$tweight"
        fi
    done

    for entry in "good first issue:+10" "help wanted:+8" "question:-5" \
        "status:blocked:-100" "status:needs-info:-100"; do
        tlabel="${entry%:*}"
        tweight="${entry##*:}"
        if has_label "$labels_list" "$tlabel"; then
            score=$((score + tweight))
            breakdown="$breakdown meta:$tlabel $tweight"
        fi
    done

    # Title signals (case-insensitive).
    local title_signals=$'leak:leak:25\ncrash-risk:crash|segfault|fatal|panic|corrupt:30\nsecurity:security|auth|authentication|authorization|xss|csrf|injection:20\nperformance:performance|perf|benchmark:15\ndead-code:dead code|unused|never throw:5'
    local line signame pat pts rest
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        signame="${line%%:*}"
        rest="${line#*:}"
        pat="${rest%:*}"
        pts="${rest##*:}"
        if [[ "$title" =~ $pat ]]; then
            score=$((score + pts))
            breakdown="$breakdown title:$signame +$pts"
        fi
    done <<<"$title_signals"

    # Age: older issues deserve attention, +0.2/day capped at 20.
    local now_epoch created_epoch days age_pts
    now_epoch="$(date +%s)"
    created_epoch="$(iso_to_epoch "$created_at")" || created_epoch=0
    days=0
    age_pts=0
    if [ -n "$created_at" ]; then
        if [ "$created_epoch" -gt 0 ] 2>/dev/null; then
            days=$(((now_epoch - created_epoch) / 86400))
            [ "$days" -lt 0 ] && days=0
            age_pts=$((days * 2 / 10))   # floor(0.2 * days)
            [ "$age_pts" -gt 20 ] && age_pts=20
            if [ "$age_pts" -gt 0 ]; then
                score=$((score + age_pts))
                breakdown="$breakdown age +$age_pts"
            fi
        fi
    fi

    # Comments: demand signal, +1 each capped at 5.
    local comment_pts="$comments"
    [ "$comment_pts" -gt 5 ] && comment_pts=5
    if [ "$comment_pts" -gt 0 ]; then
        score=$((score + comment_pts))
        breakdown="$breakdown comments +$comment_pts"
    fi

    # The labels field inside the scored line uses \x1e so it cannot
    # collide with the outer \x1f field separator.
    local labels_out="${labels_field//$SEP/$LSEP}"

    printf '%d%s%s%s%s%s%s%s%s%s%s%s%s\n' \
        "$score" "$SEP" "$number" "$SEP" "$title" "$SEP" "$labels_out" \
        "$SEP" "${breakdown# }" "$SEP" "$days" "$SEP" "$comments"
}

print_json() {
    # $1: total_issues, $2: shown, $3: scored file, $4: sorted milestones file
    local total_issues="$1" shown="$2" scored="$3" sorted="$4"
    local sep=""
    local t o c

    printf '{\n  "milestones": [\n'
    while IFS="$SEP" read -r t _ o c _; do
        printf '%s    {"title": "%s", "open_issues": %s, "closed_issues": %s}\n' \
            "$sep" "$(json_escape "$t")" "$o" "$c"
        sep=","
    done <"$sorted"

    printf '  ],\n  "picked_milestone": "%s",\n  "picked_reason": "%s",\n  "top": %s,\n  "issues": [\n' \
        "$(json_escape "$target_title")" "$(json_escape "$picked_reason")" "$shown"

    sep=""
    local s num title csv rationale days cmts lbs labels_json
    while IFS="$SEP" read -r s num title csv rationale days cmts; do
        # Reset per issue — an unlabeled issue must not inherit the
        # previous issue's labels.
        labels_json="[]"
        if [ -n "$csv" ]; then
            lbs="$(printf '%s' "$csv" | tr "$LSEP" '\n' | \
                sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e 's/.*/"&"/' | \
                paste -sd ',' -)"
            labels_json="[$lbs]"
        fi
        printf '%s    {"number": %s, "title": "%s", "labels": %s, "score": %s, "rationale": "%s", "age_days": %s, "comments": %s}\n' \
            "$sep" "$num" "$(json_escape "$title")" "$labels_json" \
            "$s" "$(json_escape "$rationale")" "$days" "$cmts"
        sep=","
    done < <(head -n "$shown" "$scored")

    printf '  ]\n}\n'
}

main() {
    parse_args "$@"

    if [ -z "$REPO" ]; then
        REPO="$(gh repo view --json nameWithOwner --jq .nameWithOwner 2>/dev/null)" || {
            echo "Cannot detect the repository. Run inside a clone or pass --repo=owner/name." >&2
            exit "$EXIT_USAGE"
        }
    fi

    # tmp dir is global so the EXIT trap can clean it up after main()
    # returns and its locals are gone.
    TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/pick-issue.XXXXXX")"
    trap 'rm -rf "$TMP_DIR"' EXIT

    # 1. List open milestones (paginated; gh caps lists at 30 per page).
    #    Columns: title\x1fnumber\x1fopen\x1fclosed
    local milestones_file="$TMP_DIR/milestones.tsv" sorted="$TMP_DIR/milestones.sorted.tsv"
    if ! gh api --paginate "repos/$REPO/milestones?state=open&per_page=100" \
        --jq '.[] | [(.title | gsub("[\u0000-\u001f\u007f]"; " ")), (.number|tostring), (.open_issues|tostring), (.closed_issues|tostring)] | join("\u001f")' \
        >"$milestones_file" 2>"$TMP_DIR/gh.err"; then
        echo "gh api failed: $(cat "$TMP_DIR/gh.err")" >&2
        exit "$EXIT_GH_ERROR"
    fi

    if [ ! -s "$milestones_file" ]; then
        echo "No open milestones found." >&2
        exit "$EXIT_GH_ERROR"
    fi

    # 2. Sort: version-like milestones first (semver numeric keys),
    #    everything else after (title tiebreak). Columns:
    #    title\x1fnumber\x1fopen\x1fclosed\x1fmaj\x1fmin\x1fpat
    : >"$sorted"
    local title number open closed
    while IFS="$SEP" read -r title number open closed; do
        if [[ "$title" =~ ^v?([0-9]+)\.([0-9]+)\.([0-9]+) ]]; then
            printf '%s%s%s%s%s%s%s%s%s%s%s%s%s\n' \
                "$title" "$SEP" "$number" "$SEP" "$open" "$SEP" "$closed" "$SEP" \
                "$((10#${BASH_REMATCH[1]}))" "$SEP" \
                "$((10#${BASH_REMATCH[2]}))" "$SEP" \
                "$((10#${BASH_REMATCH[3]}))" >>"$sorted"
        else
            printf '%s%s%s%s%s%s%s%s%s%s%s%s%s\n' \
                "$title" "$SEP" "$number" "$SEP" "$open" "$SEP" "$closed" "$SEP" \
                "999999" "$SEP" "999999" "$SEP" "999999" >>"$sorted"
        fi
    done <"$milestones_file"

    LC_ALL=C sort -t "$SEP" -k5,5n -k6,6n -k7,7n -k1,1 "$sorted" -o "$sorted"

    # 3. Pick the target milestone: explicit override or the lowest one.
    local target_title="" target_number="" target_open="" target_closed=""
    local picked_reason=""
    if [ -n "$MILESTONE" ]; then
        local found="" t n o c _m _x _y
        while IFS="$SEP" read -r t n o c _m _x _y; do
            if [ "$t" = "$MILESTONE" ]; then
                found=1
                target_title="$t"
                target_number="$n"
                target_open="$o"
                target_closed="$c"
                break
            fi
        done <"$sorted"
        if [ -z "$found" ]; then
            echo "Milestone \"$MILESTONE\" not found among open milestones." >&2
            exit "$EXIT_USAGE"
        fi
        picked_reason="explicit --milestone override"
    else
        IFS="$SEP" read -r target_title target_number target_open target_closed _m _x _y <"$sorted"
        picked_reason="lowest open milestone by version"
    fi

    # 4. Release rule: an empty milestone ends the workflow — stop here.
    if [ "$target_open" -eq 0 ]; then
        local message
        message="$(printf 'Milestone %s is complete (0 open issues left). STOP the workflow — cut the release:\n  1. Follow docs/release-workflow.md (CHANGELOG PR, annotated tag %s)\n  2. Close milestone %s\n  3. Re-run this script to pick the next milestone\n' \
            "$target_title" "$target_title" "$target_title")"
        if [ "$JSON_OUT" -eq 1 ]; then
            printf '{\n  "release_needed": true,\n  "message": "%s",\n  "milestone": {\n    "title": "%s",\n    "open_issues": 0,\n    "closed_issues": %s\n  }\n}\n' \
                "$(json_escape "$message")" "$(json_escape "$target_title")" "$target_closed"
        else
            echo "RELEASE NEEDED — workflow stopped."
            echo
            echo "$message"
        fi
        exit "$EXIT_RELEASE_NEEDED"
    fi

    # 5. Fetch open issues of the target milestone. /issues also returns
    #    pull requests — filtered out here. Bodies are never fetched.
    #    Columns: number\x1ftitle\x1fcreated_at\x1fcomments\x1flabels…
    #    (labels come LAST and are \x1f-joined within the remainder, so
    #    the field count stays variable-safe: an issue without labels
    #    simply has an empty final field)
    local issues_file="$TMP_DIR/issues.tsv"
    if ! gh api --paginate "repos/$REPO/issues?state=open&milestone=$target_number&per_page=100" \
        --jq '.[] | select(.pull_request == null) | [(.number|tostring), (.title | gsub("[\u0000-\u001f\u007f]"; " ")), .created_at, (.comments|tostring), ([.labels[].name | gsub("[\u0000-\u001f\u007f]"; " ")] | join("\u001f"))] | join("\u001f")' \
        >"$issues_file" 2>"$TMP_DIR/gh.err"; then
        echo "gh api failed: $(cat "$TMP_DIR/gh.err")" >&2
        exit "$EXIT_GH_ERROR"
    fi

    if [ ! -s "$issues_file" ]; then
        echo "Inconsistent API data: milestone $target_title reports $target_open open issue(s) but the issue request returned none." >&2
        exit "$EXIT_GH_ERROR"
    fi

    # 6. Score every issue.
    local scored="$TMP_DIR/scored.tsv"
    : >"$scored"
    local number created_at comments labels_field
    while IFS="$SEP" read -r number title created_at comments labels_field; do
        score_line "$number" "$title" "$labels_field" "$created_at" "$comments" >>"$scored"
    done <"$issues_file"

    # 7. Rank: highest score first, ties by lowest issue number.
    LC_ALL=C sort -t "$SEP" -k1,1nr -k2,2n "$scored" -o "$scored"

    local total_issues shown
    total_issues="$(wc -l <"$scored" | tr -d ' ')"
    shown="$TOP"
    if [ "$TOP" -eq 0 ] || [ "$shown" -gt "$total_issues" ]; then
        shown="$total_issues"
    fi

    # 8. Output.
    if [ "$JSON_OUT" -eq 1 ]; then
        print_json "$total_issues" "$shown" "$scored" "$sorted"
        return
    fi

    echo "Open milestones:"
    local t o c marker=""
    while IFS="$SEP" read -r t _ o c _; do
        if [ "$t" = "$target_title" ]; then
            marker="  <-- picked"
        else
            marker=""
        fi
        printf '  %s (%s open, %s closed)%s\n' "$t" "$o" "$c" "$marker"
    done <"$sorted"

    printf '\n%s — top %s of %s issue(s), ordered by score (highest first):\n\n' \
        "$target_title" "$shown" "$total_issues"

    local rank=1 s num csv rationale days cmts labels_display=""
    while IFS="$SEP" read -r s num title csv rationale days cmts; do
        [ "$rank" -gt "$shown" ] && break
        if [ -n "$csv" ]; then
            # Human-readable: \x1e-joined labels back to comma-separated.
            labels_display="$(printf '%s' "$csv" | tr "$LSEP" ',')"
        else
            labels_display="no labels"
        fi
        printf '%2d. #%s  (%3d pts)  [%s] %s\n' "$rank" "$num" "$s" "$labels_display" "$title"
        printf '     %s\n' "$rationale"
        rank=$((rank + 1))
    done <"$scored"

    local best_line best_score best_num best_t
    best_line="$(head -n 1 "$scored")"
    if [ -n "$best_line" ]; then
        IFS="$SEP" read -r best_score best_num best_t _x _y _z _w <<<"$best_line"
        printf '\nHighest-scoring candidate: #%s (%s pts) — %s\n' "$best_num" "$best_score" "$best_t"
    fi
    echo "Pick one of these, then follow docs/workflow.md."
}

main "$@"
