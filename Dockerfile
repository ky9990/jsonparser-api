FROM php:7.0.9-apache

RUN apt-get update && apt-get install -y ssh

RUN apt-get install -y zlib1g zlib1g-dev && \
 docker-php-ext-install zip && \
 docker-php-ext-enable zip

COPY . /var/www/html
COPY ./php-apache/json-parser.conf /etc/apache2/sites-enabled/json-parser.conf
COPY ./php-apache/security.conf /etc/apache2/conf-enabled/security.conf
COPY ./php-apache/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php \
  && mv composer.phar /usr/local/bin/composer

RUN composer install --prefer-dist --no-interaction

RUN a2enmod rewrite
RUN a2enmod headers
RUN a2dissite 000-default

RUN chown -R www-data:www-data /var/www/html/app/cache
RUN chown -R www-data:www-data /var/www/html/app/logs