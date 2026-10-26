# PHP 8.2 con Apache y HTTPS para Pahuichi POS
FROM php:8.2-apache-bookworm

# Extensiones: base de datos, imágenes, formatos locales, Excel y cobertura de pruebas
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl zip pcov opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip openssl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod ssl rewrite headers

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/pahuichi.ini
COPY entrypoint.sh /usr/local/bin/pahuichi-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/pahuichi-entrypoint \
    && chmod +x /usr/local/bin/pahuichi-entrypoint

WORKDIR /var/www/pahuichi

ENTRYPOINT ["pahuichi-entrypoint"]
CMD ["apache2-foreground"]
