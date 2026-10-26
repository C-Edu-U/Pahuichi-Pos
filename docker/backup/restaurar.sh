#!/bin/sh
# Restaura solo en otra base vacia. Nunca vacia ni reemplaza la base de ventas.
set -eu
: "${DB_HOST:=db}" "${DB_PUERTO:=3306}" "${DB_NOMBRE:=pahuichi}" "${BACKUP_DIR:=/respaldos}"
: "${DB_CLAVE_ROOT:?Falta DB_CLAVE_ROOT}"
export MYSQL_PWD="$DB_CLAVE_ROOT"
[ "$#" -eq 2 ] || { echo 'Uso: restaurar.sh archivo.sql nombre_base_vacia' >&2; exit 1; }
archivo=$1
destino=$2
case "$archivo" in
    pahuichi-????-??-??_??????.??????.sql) ;;
    *) echo 'HU-07: nombre de copia no permitido.' >&2; exit 1 ;;
esac
case "$archivo" in */*) echo 'HU-07: usa solo el nombre del archivo.' >&2; exit 1 ;; esac
case "$destino" in ''|*[!a-zA-Z0-9_]*|mysql|information_schema|performance_schema|sys)
    echo 'HU-07: nombre de base de destino no permitido.' >&2; exit 1 ;; esac
[ "${#destino}" -le 64 ] || { echo 'HU-07: nombre de base demasiado largo.' >&2; exit 1; }
# Comparacion sin distinguir mayusculas: no se permite restaurar sobre la base habitual.
normal=$(printf '%s' "$destino" | tr '[:upper:]' '[:lower:]')
case "$normal" in mysql|information_schema|performance_schema|sys) echo 'HU-07: base reservada.' >&2; exit 1 ;; esac
base=$(printf '%s' "$DB_NOMBRE" | tr '[:upper:]' '[:lower:]')
[ "$normal" != "$base" ] || { echo 'HU-07: usa otra base vacia; no se modifica la base habitual.' >&2; exit 1; }
[ -f "$BACKUP_DIR/$archivo" ] && [ ! -L "$BACKUP_DIR/$archivo" ] && [ -s "$BACKUP_DIR/$archivo" ] || { echo 'HU-07: copia no encontrada o vacia.' >&2; exit 1; }
sql() { mariadb --host="$DB_HOST" --port="$DB_PUERTO" --user=root --batch --skip-column-names "$@"; }
# No se usa DROP DATABASE ni se permite importar sobre datos existentes.
conteo=$(sql --execute="SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$destino') + (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$destino') + (SELECT COUNT(*) FROM information_schema.events WHERE event_schema='$destino')")
[ "$conteo" = 0 ] || { echo 'HU-07: la base de destino contiene tablas. No se importo nada.' >&2; exit 1; }
sql --execute="CREATE DATABASE IF NOT EXISTS \`$destino\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
# Las cuentas creadas por la imagen oficial usan host %. Se conserva esa cuenta.
if [ -n "${DB_USUARIO:-}" ]; then
    case "$DB_USUARIO" in *[!a-zA-Z0-9_]*) echo 'HU-07: usuario no permitido para otorgar acceso.' >&2; exit 1 ;; esac
    cuenta=$(sql --execute="SELECT COUNT(*) FROM mysql.user WHERE User='$DB_USUARIO' AND Host='%'")
    [ "$cuenta" = 1 ] || { echo 'HU-07: no se encontro la cuenta de la aplicacion.' >&2; exit 1; }
fi
sql "$destino" < "$BACKUP_DIR/$archivo"
if [ -n "${DB_USUARIO:-}" ]; then
    sql --execute="GRANT ALL PRIVILEGES ON \`$destino\`.* TO '$DB_USUARIO'@'%'"
fi
echo "HU-07: copia restaurada en $destino. La base habitual se conservo."
