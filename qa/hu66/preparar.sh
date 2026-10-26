#!/bin/bash
set -euo pipefail
test "${HU66_AISLADO:-}" = 1 && test "${DB_NOMBRE:-}" = pahuichi_qa
cd /var/www/pahuichi
# Solo se limpia la instantánea del volumen de QA; /origen está montado de solo lectura.
find . -mindepth 1 -maxdepth 1 ! -name vendor -exec rm -rf -- {} +
tar -C /origen --exclude='./vendor' --exclude='./.git' --exclude='./.env' --exclude='./storage' --exclude='./respaldos' --exclude='./var' --exclude='./docker/certs' --exclude='./qa/hu66/evidencias' -cf - . | tar -xf -
mkdir -p storage/subidas /evidencias
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --no-progress --prefer-dist
php bin/consola.php esperar-db
php bin/consola.php migrar
php -v > /evidencias/php-version.txt
composer show --format=json > /evidencias/dependencias.json
find app tests config database -type f -print0 | sort -z | xargs -0 sha256sum > /evidencias/instantanea.sha256
chown -R www-data:www-data storage
echo 'Instantanea de QA preparada. La carpeta original y la base habitual no se modificaron.'
