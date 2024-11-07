# Gunakan image PHP dengan FPM
FROM php:8.2-fpm as php

# Set pengguna root untuk menghindari masalah izin
USER root

# Pastikan direktori /var/lib/apt/lists ada dan memiliki izin yang tepat
RUN mkdir -p /var/lib/apt/lists && chmod -R 755 /var/lib/apt/lists

# Update dan install dependencies dasar
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    libssl-dev \
    && docker-php-ext-install pdo_mysql bcmath sockets \
    && rm -rf /var/lib/apt/lists/*


# Install Composer dari image resmi Composer
COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

# Tentukan direktori kerja
WORKDIR /var/www

# Copy semua file Laravel ke container
COPY . .

# Install dependensi Laravel dengan Composer
RUN composer install --optimize-autoloader --no-dev

# Set permissions untuk direktori storage dan bootstrap Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Jalankan perintah Laravel untuk optimasi (opsional)
RUN php artisan config:cache
RUN php artisan route:cache

# Tentukan port yang diekspos oleh PHP-FPM
EXPOSE 9000

# Set pengguna kembali ke www-data untuk keamanan
USER www-data

# Jalankan PHP-FPM sebagai perintah utama container
CMD ["php-fpm"]
