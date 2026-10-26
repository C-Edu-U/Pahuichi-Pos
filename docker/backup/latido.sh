#!/bin/sh
set -eu
: "${BACKUP_DIR:=/respaldos}" "${BACKUP_HORA:=02:00}"
: "${BACKUP_ESTADO_DIR:=/estado}"
trap 'exit 0' HUP INT TERM
while :; do
    ahora=$(date +%s); hoy=$(date +%Y-%m-%d)
    proxima=$(date -d "$hoy $BACKUP_HORA:00" +%s)
    existe=$(find "$BACKUP_DIR" -maxdepth 1 -type f -size +0c -name "pahuichi-${hoy}_??????.??????.sql" -print -quit)
    if [ "$proxima" -le "$ahora" ]; then
        if [ -n "$existe" ]; then proxima=$(date -d "tomorrow $BACKUP_HORA:00" +%s); else proxima=$ahora; fi
    fi
    printf '{"ahora":%s,"hora":"%s","proxima":%s}\n' "$ahora" "$BACKUP_HORA" "$proxima" > "$BACKUP_ESTADO_DIR/.estado.tmp"
    chmod 644 "$BACKUP_ESTADO_DIR/.estado.tmp"
    mv "$BACKUP_ESTADO_DIR/.estado.tmp" "$BACKUP_ESTADO_DIR/.estado.json"
    sleep 1 & wait $! || true
done
