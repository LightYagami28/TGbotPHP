# syntax=docker/dockerfile:1

# Build stage: compilers, git/unzip and composer live here only.
FROM php:8.5-cli AS build

WORKDIR /app

# pcntl lets the polling example stop cleanly on SIGTERM
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-interaction --no-progress --no-autoloader --prefer-dist

# Only what the bot needs at runtime: no tests, docs, .env or VCS files
COPY bin/ bin/
COPY src/ src/
COPY examples/ examples/
RUN composer dump-autoload --no-dev --optimize

# Runtime stage: same PHP base, but without git, unzip or composer.
FROM php:8.5-cli

WORKDIR /app

COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN useradd --system --no-create-home --shell /usr/sbin/nologin bot

COPY --from=build --chown=root:root /app /app

USER bot

# Long polling by default; override the command to run your own bot
CMD ["php", "examples/polling.php"]
