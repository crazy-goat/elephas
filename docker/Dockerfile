FROM php:8.2-cli-alpine AS base

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        linux-headers \
        gmp \
        gmp-dev \
    && docker-php-ext-install \
        gmp \
        bcmath \
        pcntl \
        posix \
        ffi \
    && apk del $PHPIZE_DEPS linux-headers gmp-dev

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS tigerbeetle-build

ARG TB_VERSION=0.17.4
ARG TARGETARCH
ARG TARGETOS

RUN if [ "$TARGETOS" = "linux" ] && [ "$TARGETARCH" = "amd64" ]; then \
        curl -Lo /tmp/tigerbeetle.zip "https://linux.tigerbeetle.com/tigerbeetle-${TB_VERSION}-x86_64-linux-gnu.zip" \
        && unzip /tmp/tigerbeetle.zip -d /usr/local/bin \
        && rm /tmp/tigerbeetle.zip; \
    elif [ "$TARGETOS" = "linux" ] && [ "$TARGETARCH" = "arm64" ]; then \
        curl -Lo /tmp/tigerbeetle.zip "https://linux.tigerbeetle.com/tigerbeetle-${TB_VERSION}-aarch64-linux-gnu.zip" \
        && unzip /tmp/tigerbeetle.zip -d /usr/local/bin \
        && rm /tmp/tigerbeetle.zip; \
    fi

FROM base

COPY --from=tigerbeetle-build /usr/local/bin/tigerbeetle /usr/local/bin/tigerbeetle

RUN ln -s /usr/local/bin/tigerbeetle /usr/local/bin/tb
