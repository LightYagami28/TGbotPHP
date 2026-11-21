# syntax=docker/dockerfile:1

# Base images pinned by digest (tag resolved 2026-11-21 from Docker Hub):
# php:8.5-cli    -> sha256:f324fcb4dc009ac0470a7d614cd122499dcee3ca47a9bab620fc14c0eb759ba2
# composer:2     -> sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93
# Refresh both together with `docker buildx imagetools inspect <image>:<tag>`.

# Build stage: compilers, git/unzip and composer live here only.
FROM php:8.5-cli@sha256:f324fcb4dc009ac0470a7d614cd122499dcee3ca47a9bab620fc14c0eb759ba2 AS build

WORKDIR /app

# pcntl lets the polling example stop cleanly on SIGTERM
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-interaction --no-progress --no-autoloader --prefer-dist

# Only what the bot needs at runtime: no tests, docs, .env or VCS files
COPY bin/ bin/
COPY src/ src/
COPY examples/ examples/
RUN composer dump-autoload --no-dev --optimize

# Runtime stage: same pinned PHP base, but without git, unzip or composer.
FROM php:8.5-cli@sha256:f324fcb4dc009ac0470a7d614cd122499dcee3ca47a9bab620fc14c0eb759ba2

WORKDIR /app

COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN useradd --system --no-create-home --shell /usr/sbin/nologin bot

COPY --from=build --chown=root:root /app /app

USER bot

# Long polling by default; override the command to run your own bot
CMD ["php", "examples/polling.php"]
