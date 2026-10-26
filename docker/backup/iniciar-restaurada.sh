#!/bin/sh
set -eu
raiz=/var/www/pahuichi
cd "$raiz"
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


# Codigo de solo lectura y fotos de prueba en un volumen separado.
mkdir -p "$raiz/storage/subidas"
if [ -d /fotos-originales ]; then cp -a /fotos-originales/. "$raiz/storage/subidas/"; fi
chown -R www-data:www-data "$raiz/storage/subidas"
printf '%s\n' 'auto_prepend_file=/var/www/pahuichi/docker/backup/contexto-restaurado.php' > /usr/local/etc/php/conf.d/zz-hu07-restauracion.ini
printf '%s\n' 'HU-07: instancia de prueba preparada; esperando la primera restauracion.'
trap 'exit 0' HUP INT TERM
while [ ! -s "$raiz/var/respaldos/.restauracion.json" ]; do sleep 1 & wait $! || true; done
# No se ejecutan migraciones ni seeds sobre una restauracion.
exec docker-php-entrypoint apache2-foreground
