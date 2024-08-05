FROM php:8.3-cli

RUN docker-php-ext-install mysqli

COPY . /var/www/html

WORKDIR /var/www/html

CMD sleep 1 && php test.php