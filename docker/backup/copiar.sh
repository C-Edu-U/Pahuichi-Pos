#!/bin/sh
# HU-07. Copia consistente, publicacion atomica y retencion solo tras el exito.
set -eu
umask 077
: "${DB_HOST:=db}" "${DB_PUERTO:=3306}" "${DB_NOMBRE:=pahuichi}" "${BACKUP_DIR:=/respaldos}"
: "${DB_CLAVE_ROOT:?Falta DB_CLAVE_ROOT}"
export MYSQL_PWD="$DB_CLAVE_ROOT"
mkdir -p "$BACKUP_DIR"
chmod 755 "$BACKUP_DIR"
fecha=$(date +%Y-%m-%d_%H%M%S)
temporal=$(mktemp "$BACKUP_DIR/.pahuichi-$fecha.XXXXXX")
trap 'rm -f "$temporal"' EXIT
trap 'exit 1' HUP INT TERM
mariadb-dump --host="$DB_HOST" --port="$DB_PUERTO" --user=root \
    --single-transaction --quick --routines --triggers --events --hex-blob \
    --default-character-set=utf8mb4 "$DB_NOMBRE" > "$temporal"
[ -s "$temporal" ] || { echo 'HU-07: la copia esta vacia.' >&2; exit 1; }
# El nombre no es visible como copia valida mientras se esta escribiendo.
nombre=$(basename "$temporal" | sed 's/^\.//').sql
mv "$temporal" "$BACKUP_DIR/$nombre"
echo "HU-07: copia creada: $nombre"
# 30 dias exactos en minutos. No se tocan subcarpetas, enlaces ni otros archivos.
scripts=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
sh "$scripts/limpiar.sh"
