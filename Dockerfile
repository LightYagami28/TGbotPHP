FROM php:8.4-cli

WORKDIR /app

# pcntl lets the polling example stop cleanly on SIGTERM
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home bot

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-autoloader

# Only what the bot needs at runtime: no tests, docs, .env or VCS files
COPY bin/ bin/
COPY src/ src/
COPY examples/ examples/
RUN composer dump-autoload --no-dev --optimize

USER bot

# Long polling by default; override the command to run your own bot
CMD ["php", "examples/polling.php"]
