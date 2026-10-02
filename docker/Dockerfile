FROM php:8.2-cli-alpine AS base

# DL3018: Alpine packages follow the php image's Alpine release, pinning them would break on base updates.
# SC2086: $PHPIZE_DEPS is a space-separated list from the base image and must be word-split.
# hadolint ignore=DL3018,SC2086
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        linux-headers \
        gmp \
        gmp-dev \
        libffi \
        libffi-dev \
    && docker-php-ext-install \
        gmp \
        bcmath \
        pcntl \
        posix \
        ffi \
    && apk del $PHPIZE_DEPS linux-headers gmp-dev libffi-dev

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS tigerbeetle-build

ARG TB_VERSION=0.17.4

# TARGETOS and TARGETARCH are injected by BuildKit only. The classic builder
# leaves both empty, no download happens and the COPY below fails with
# "stat usr/local/bin/tigerbeetle: file does not exist". Derive the platform
# inside the stage instead; under a cross-platform build the RUN step executes
# under emulation, so uname still reports the target architecture.
# -f makes curl fail on an HTTP error instead of saving the error page.
RUN set -eu; \
    case "$(uname -m)" in \
        x86_64) arch=x86_64 ;; \
        aarch64) arch=aarch64 ;; \
        *) echo "Unsupported architecture: $(uname -m). Expected x86_64 or aarch64." >&2; exit 1 ;; \
    esac; \
    curl -fLo /tmp/tigerbeetle.zip "https://linux.tigerbeetle.com/tigerbeetle-${TB_VERSION}-${arch}-linux-gnu.zip"; \
    unzip /tmp/tigerbeetle.zip -d /usr/local/bin; \
    rm /tmp/tigerbeetle.zip

FROM base

COPY --from=tigerbeetle-build /usr/local/bin/tigerbeetle /usr/local/bin/tigerbeetle

RUN ln -s /usr/local/bin/tigerbeetle /usr/local/bin/tb
