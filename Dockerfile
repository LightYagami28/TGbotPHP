FROM php:8.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize && chmod +x bin/tgbot

RUN useradd --create-home bot
USER bot

# Long polling by default; override the command to run your own bot
CMD ["php", "examples/polling.php"]
