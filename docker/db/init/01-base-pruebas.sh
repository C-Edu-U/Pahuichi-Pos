#!/bin/bash
# Base aparte para las pruebas automáticas (PHPUnit la vacía en cada corrida).
# Solo corre la primera vez, cuando el volumen de datos está vacío.
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}_pruebas\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}_pruebas\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
