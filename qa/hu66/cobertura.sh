#!/bin/bash
set -euo pipefail
test "${HU66_AISLADO:-}" = 1 && test "${DB_NOMBRE:-}" = pahuichi_qa
cd /var/www/pahuichi
mkdir -p /evidencias/cobertura
rm -f /evidencias/clover.xml /evidencias/junit.xml
set +e
php -d pcov.enabled=1 -d pcov.directory=/var/www/pahuichi/app vendor/bin/phpunit --coverage-html /evidencias/cobertura --coverage-clover /evidencias/clover.xml --log-junit /evidencias/junit.xml --coverage-text=/evidencias/cobertura.txt --colors=never 2>&1 | tee /evidencias/phpunit.log
codigo=${PIPESTATUS[0]}
set -e
echo "$codigo" > /evidencias/phpunit-exit.txt
php /qa/evaluar.php cobertura
