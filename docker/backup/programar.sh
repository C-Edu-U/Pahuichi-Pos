#!/bin/sh
set -eu
: "${BACKUP_HORA:=02:00}" "${BACKUP_DIR:=/respaldos}"
: "${BACKUP_ESTADO_DIR:=/estado}"
case "$BACKUP_HORA" in [01][0-9]:[0-5][0-9]|2[0-3]:[0-5][0-9]) ;; *) echo 'HU-07: BACKUP_HORA debe tener formato HH:MM.' >&2; exit 1 ;; esac
scripts=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
mkdir -p "$BACKUP_DIR" "$BACKUP_ESTADO_DIR"
chmod 755 "$BACKUP_DIR" "$BACKUP_ESTADO_DIR"
solicitudes="$BACKUP_ESTADO_DIR/.solicitudes"
mkdir -p "$solicitudes"
# Solo recibe solicitudes acotadas. Los SQL y el estado del servicio siguen protegidos.
chmod 1777 "$solicitudes"
umask 077
sh "$scripts/latido.sh" &
latidoPid=$!
trap 'kill "$latidoPid" 2>/dev/null || true' EXIT
trap 'exit 0' HUP INT TERM
reintentar=0
marcar() {
    estado=$1; mensaje=$2
    temporal="$solicitudes/.$id.tmp"
    printf '{"id":"%s","estado":"%s","accion":"%s","archivo":"%s","programada":%s,"mensaje":"%s"}\n' "$id" "$estado" "$accion" "$archivo" "$programada" "$mensaje" > "$temporal"
    chmod 644 "$temporal"
    mv "$temporal" "$solicitudes/$id.json"
}
echo "HU-07: copia diaria a las $BACKUP_HORA, zona ${TZ:-UTC}; retencion 30 dias. Simulacion disponible en Configuracion."
while :; do
    ahora=$(date +%s); hoy=$(date +%Y-%m-%d); hora=$(date +%H%M)
    objetivo=$(printf '%s' "$BACKUP_HORA" | tr -d ':')
    existe=$(find "$BACKUP_DIR" -maxdepth 1 -type f -size +0c -name "pahuichi-${hoy}_??????.??????.sql" -print -quit)
    if [ "$hora" -ge "$objetivo" ] && [ -z "$existe" ] && [ "$ahora" -ge "$reintentar" ]; then
        if ! sh "$scripts/copiar.sh"; then echo 'HU-07: fallo la copia; se reintentara en un minuto.' >&2; reintentar=$((ahora + 60)); fi
    fi
    for peticion in "$solicitudes"/*.req; do
        [ -f "$peticion" ] && [ ! -L "$peticion" ] || continue
        id=$(basename "$peticion" .req)
        case "$id" in *[!a-f0-9]*) continue ;; esac
        [ "${#id}" -eq 32 ] || continue
        # Un trabajo terminado nunca se vuelve a ejecutar, incluso tras un reinicio.
        if [ -f "$solicitudes/$id.json" ] && grep -Eq '"estado"[[:space:]]*:[[:space:]]*"(hecho|error)"' "$solicitudes/$id.json"; then rm -f "$peticion"; continue; fi
        if ! { IFS= read -r accion; IFS= read -r archivo; IFS= read -r programada; } < "$peticion"; then rm -f "$peticion"; continue; fi
        case "$accion" in copiar|vencer|restaurar) ;; *) rm -f "$peticion"; continue ;; esac
        case "$programada" in ''|*[!0-9]*) rm -f "$peticion"; continue ;; esac
        [ "${#programada}" -le 12 ] || { rm -f "$peticion"; continue; }
        if [ "$accion" != copiar ]; then
            case "$archivo" in *[!a-zA-Z0-9_.-]*) rm -f "$peticion"; continue ;; esac
            case "$archivo" in pahuichi-????-??-??_??????.??????.sql) ;; *) rm -f "$peticion"; continue ;; esac
        else archivo=''; fi
        [ "$ahora" -ge "$programada" ] || continue
        if [ "$accion" = restaurar ]; then
            sh "$scripts/probar-restauracion.sh" "$archivo" "$id" "$programada" || true
            rm -f "$peticion"
            continue
        fi
        marcar ejecutando 'Generando una copia nueva...'
        valido=1
        if [ "$accion" = vencer ] && { [ ! -f "$BACKUP_DIR/$archivo" ] || [ -L "$BACKUP_DIR/$archivo" ]; }; then valido=0; fi
        if [ "$valido" = 0 ]; then
            marcar error 'La copia elegida ya no esta disponible.'
        elif sh "$scripts/copiar.sh"; then
            if [ "$accion" = vencer ]; then
                if [ -f "$BACKUP_DIR/$archivo" ]; then
                    # El reemplazo ya esta publicado. Aplicar vencimiento y confirmar la eliminacion.
                    if ! touch -d '31 days ago' "$BACKUP_DIR/$archivo" || ! sh "$scripts/limpiar.sh"; then
                        marcar error 'La copia nueva se creo, pero no se pudo completar la limpieza.'
                        rm -f "$peticion"
                        continue
                    fi
                    rm -f -- "$BACKUP_DIR/$archivo"
                fi
                if [ ! -e "$BACKUP_DIR/$archivo" ]; then
                    marcar hecho 'Simulacion terminada: copia nueva creada y copia seleccionada eliminada por vencimiento.'
                else marcar error 'La copia nueva se creo, pero el archivo seleccionado no se pudo eliminar.'; fi
            else marcar hecho 'Simulacion terminada: copia nueva creada correctamente.'; fi
        else
            marcar error 'No se pudo crear la copia nueva. No se envejecio la copia seleccionada.'
        fi
        rm -f "$peticion"
    done
    find "$solicitudes" -maxdepth 1 -type f -name '*.json' -mmin +2880 -delete
    sleep 1 &
    wait $! || true
done
