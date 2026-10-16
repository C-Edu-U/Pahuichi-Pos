#!/bin/sh
# Arranque del contenedor app: certificado, dependencias, migraciones y Apache.
set -e
cd /var/www/pahuichi

# Certificado HTTPS: usa el de docker/certs (mkcert) si existe; si no, uno autofirmado
CERTS=/etc/apache2/certs
mkdir -p "$CERTS"
if [ -f docker/certs/pahuichi.crt ] && [ -f docker/certs/pahuichi.key ]; then
    cp docker/certs/pahuichi.crt docker/certs/pahuichi.key "$CERTS/"
elif [ ! -f "$CERTS/pahuichi.crt" ]; then
    echo "Pahuichi: generando certificado autofirmado (para uno de confianza, ver README)"
    openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
        -addext "basicConstraints=critical,CA:FALSE" \
        -keyout "$CERTS/pahuichi.key" -out "$CERTS/pahuichi.crt" 2>/dev/null
fi

# Dependencias de Composer (en un volumen propio, fuera de la carpeta del proyecto)
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --no-progress --prefer-dist --quiet

# Carpetas donde escribe la app
mkdir -p storage/subidas
chown -R www-data:www-data storage 2>/dev/null || true

# Base de datos: espera, migra y carga los datos de prueba la primera vez
php bin/consola.php esperar-db
php bin/consola.php migrar

exec docker-php-entrypoint "$@"
