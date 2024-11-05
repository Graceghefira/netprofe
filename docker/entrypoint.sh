#!/bin/bash


if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-progress --no-interaction
fi

if [ ! -f ".env" ]; then
    echo "Creating env file for env $APP_ENV"
    cp .env.example .env
    php artisan key:generate
else
    echo "env file exists."
fi

role=${CONTAINER_ROLE:-app}

if [ "$role" = "app" ]; then
    php artisan migrate
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    exec php-fpm

elif [ "$role" = "queue" ]; then
    echo "Running the queue ... "
    php /var/www/artisan queue:work --verbose --tries=3 --timeout=180

elif [ "$role" = "websocket" ]; then
    echo "Running the websocket server ... "
    php artisan websockets:serve
fi
