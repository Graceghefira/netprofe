# PHP Stage
FROM php:8.1-fpm as php

# Update and install necessary dependencies and PHP extensions
RUN apt-get update -y && \
    apt-get install -y unzip libpq-dev libcurl4-gnutls-dev && \
    docker-php-ext-install pdo pdo_mysql bcmath && \
    pecl install -o -f redis && \
    rm -rf /tmp/pear && \
    docker-php-ext-enable redis && \
    apt-get install -y build-essential libpng-dev \
    libjpeg62-turbo-dev libfreetype6-dev locales zip jpegoptim optipng \
    pngquant gifsicle vim unzip git curl libzip-dev libonig-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Add Redis extension to php.ini
RUN echo "extension=redis.so" >> /usr/local/etc/php/conf.d/redis.ini

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Install Composer
COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

# Copy entrypoint script and set permissions
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions and user
RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www && \
    chown -R www:www /var/www
USER www
ENV PORT=8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ======================================================================
# Node Stage
FROM node:14-alpine as node

WORKDIR /var/www
COPY . .

RUN npm install --global cross-env && \
    npm install

VOLUME /var/www/node_modules
EXPOSE 8000 9000
CMD ["php-fpm"]

