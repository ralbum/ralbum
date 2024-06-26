# Use an official PHP runtime as a parent image
FROM php:8.2-apache

# Install mod_rewrite for Apache
RUN a2enmod rewrite

# Set the working directory in the container
WORKDIR /var/www/html

# Install PHP extensions and other dependencies
RUN apt-get update \
	&& apt-get install -y libzip-dev zip git libmagickwand-dev vim sudo \
	&& pecl install imagick \
	&& docker-php-ext-enable imagick \
	&& docker-php-ext-install exif 

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer

# Install Ralbum 
RUN cd /var/www/html && git clone https://github.com/ralbum/ralbum.git .
RUN cd /var/www/html/app && /usr/local/bin/composer install

# Set permissions for cache and data
RUN chmod 775 /var/www/html/cache
RUN chmod 775 /var/www/html/data
RUN chown www-data:www-data /var/www/html/cache
RUN chown www-data:www-data /var/www/html/data

COPY ralbum_cron.sh .

# Expose the port Apache listens on
EXPOSE 80

# Start Apache when the container runs
CMD ["apache2-foreground"]

