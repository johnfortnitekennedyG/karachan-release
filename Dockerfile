FROM php:8.1-fpm

# install dependencies
RUN apt-get update && apt-get install -y libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libmagickwand-dev imagemagick unzip git curl && rm -rf /var/lib/apt/lists/*
	
# install php extensions
RUN docker-php-ext-install bcmath pdo pdo_mysql mbstring iconv xml zip

# install imagick
RUN pecl install imagick && docker-php-ext-enable imagick

# install redis from PECL
RUN pecl install redis && docker-php-ext-enable redis

# harden php config
RUN echo "expose_php=Off" >> /usr/local/etc/php/php.ini && sed -i 's/^pm.status_path/# pm.status_path/' /usr/local/etc/php-fpm.d/www.conf && sed -i 's/^ping.path/# ping.path/' /usr/local/etc/php-fpm.d/www.conf

# install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# work dir
WORKDIR /var/www/html