FROM php:8.2-apache

RUN a2enmod rewrite

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y \
        libzip-dev \
        zip \
        libmagickwand-dev \
        git \
        sudo \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install exif gd \
    && rm -rf /var/lib/apt/lists/*

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer

RUN git clone https://github.com/ralbum/ralbum.git . \
    && cd app && /usr/local/bin/composer install --no-dev --optimize-autoloader \
    && chmod 775 /var/www/html/cache /var/www/html/data \
    && chown www-data:www-data /var/www/html/cache /var/www/html/data

COPY ralbum_cron.sh .

EXPOSE 80
CMD ["apache2-foreground"]