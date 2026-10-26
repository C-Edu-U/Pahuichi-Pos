FROM php:8.2-apache-bookworm
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mbstring dom xml xmlwriter simplexml gd intl zip pcov opcache
RUN echo 'pcov.enabled=0' > /usr/local/etc/php/conf.d/zz-hu66.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip openssl ca-certificates && rm -rf /var/lib/apt/lists/* && a2enmod ssl rewrite headers
COPY docker/app/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/pahuichi.ini
COPY docker/app/entrypoint.sh /usr/local/bin/pahuichi-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/pahuichi-entrypoint && chmod +x /usr/local/bin/pahuichi-entrypoint
WORKDIR /var/www/pahuichi
ENTRYPOINT ["pahuichi-entrypoint"]
CMD ["apache2-foreground"]
