#!/bin/sh
set -eu
umask 077
: "${BACKUP_DIR:=/respaldos}" "${DB_HOST:=db}" "${DB_PUERTO:=3306}"
: "${BACKUP_ESTADO_DIR:=/estado}"
: "${DB_CLAVE_ROOT:?Falta DB_CLAVE_ROOT}"
[ "$#" -eq 3 ] || exit 1
archivo=$1; id=$2; programada=$3
case "$id" in ''|*[!a-f0-9]*) exit 1 ;; esac
[ "${#id}" -eq 32 ] || exit 1
case "$archivo" in *[!a-zA-Z0-9_.-]*) exit 1 ;; esac
case "$archivo" in pahuichi-????-??-??_??????.??????.sql) ;; *) exit 1 ;; esac
case "$programada" in ''|*[!0-9]*) exit 1 ;; esac
scripts=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
export MYSQL_PWD="$DB_CLAVE_ROOT"
destino="pahuichi_hu07_demo_$id"
resultado="$BACKUP_ESTADO_DIR/.solicitudes/$id.json"
terminada=0
publicada=0
etapa() {
    printf '{"id":"%s","accion":"restaurar","archivo":"%s","programada":%s,"estado":"%s","mensaje":"%s"}\n' "$id" "$archivo" "$programada" "$1" "$2" > "$resultado.tmp"
    chmod 644 "$resultado.tmp"; mv "$resultado.tmp" "$resultado"
}
finalizar() {
    if [ "$terminada" = 0 ]; then
        if [ "$publicada" = 1 ]; then etapa error 'Los datos se restauraron, pero la instancia de prueba no esta disponible. La base habitual se conservo.';
        else etapa error 'No se pudo completar la restauracion. La base habitual y la restauracion anterior se conservaron.'; fi
    fi
}
trap finalizar EXIT
trap 'exit 1' HUP INT TERM
sql() { mariadb --host="$DB_HOST" --port="$DB_PUERTO" --user=root --batch --skip-column-names "$@"; }
etapa ejecutando 'Preparando otra base de datos vacia...'
existe=$(sql --execute="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$destino'")
[ "$existe" = 0 ] || { echo 'HU-07: el destino de esta prueba ya existe; no se reemplaza.' >&2; exit 1; }
sql --execute="CREATE DATABASE \`$destino\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
vacias=$(sql --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$destino'")
[ "$vacias" = 0 ] || exit 1
etapa ejecutando 'Base vacia creada: 0 tablas. Preparando la importacion...'
sleep 1
etapa ejecutando 'Restaurando tablas y datos desde la copia seleccionada...'
sh "$scripts/restaurar.sh" "$archivo" "$destino"
sleep 1
etapa ejecutando 'Comprobando usuarios, productos, ventas, facturas y pagos recuperados...'
sleep 1
# Cuenta todos los datos relevantes y confirma las tablas antes de publicar la instancia.
sql "$destino" --execute="SELECT JSON_OBJECT('id','$id','accion','restaurar','archivo','$archivo','programada',$programada,'estado','hecho','mensaje','Restauracion terminada en una base nueva. Ya puedes abrir el sistema restaurado.','base','$destino','vacia_antes',TRUE,'lista',0,'datos',JSON_OBJECT('usuarios',(SELECT COUNT(*) FROM usuarios),'productos',(SELECT COUNT(*) FROM productos),'ventas',(SELECT COUNT(*) FROM ventas),'facturas',(SELECT COUNT(*) FROM facturas),'pagos',(SELECT COUNT(*) FROM pagos),'cajas',(SELECT COUNT(*) FROM cajas),'bitacora',(SELECT COUNT(*) FROM bitacora)))" > "$resultado.verificado"
[ -s "$resultado.verificado" ] || exit 1
# La app de prueba lee este destino por peticion. Nunca modifica la configuracion habitual.
cp "$resultado.verificado" "$BACKUP_ESTADO_DIR/.restauracion.json.tmp"
chmod 644 "$BACKUP_ESTADO_DIR/.restauracion.json.tmp"
mv "$BACKUP_ESTADO_DIR/.restauracion.json.tmp" "$BACKUP_ESTADO_DIR/.restauracion.json"
publicada=1
etapa ejecutando 'Datos recuperados. Esperando que inicie el sistema restaurado...'
if [ -n "${RESTAURACION_HOST:-}" ]; then
    disponible=0
    for intento in $(seq 1 30); do
        if timeout 2 bash -c 'exec 3<>/dev/tcp/"$1"/"$2"' _ "$RESTAURACION_HOST" "${RESTAURACION_PUERTO_INTERNO:-443}" 2>/dev/null; then disponible=1; break; fi
        sleep 1
    done
    [ "$disponible" = 1 ] || exit 1
fi
sed 's/"lista"[[:space:]]*:[[:space:]]*0/"lista":1/' "$resultado.verificado" > "$resultado.listo"
mv "$resultado.listo" "$resultado.verificado"
cp "$resultado.verificado" "$BACKUP_ESTADO_DIR/.restauracion.json.tmp"
chmod 644 "$BACKUP_ESTADO_DIR/.restauracion.json.tmp"
mv "$BACKUP_ESTADO_DIR/.restauracion.json.tmp" "$BACKUP_ESTADO_DIR/.restauracion.json"
chmod 644 "$resultado.verificado"; mv "$resultado.verificado" "$resultado"
terminada=1
echo "HU-07: prueba de restauracion terminada en $destino. Base habitual conservada."
