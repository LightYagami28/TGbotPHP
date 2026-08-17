FROM php:8.4-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock* ./

RUN curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN chmod +x bin/tgbot

ENTRYPOINT ["php"]
CMD ["-S", "localhost:8000", "-t", "."]
