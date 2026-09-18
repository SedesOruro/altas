#!/bin/sh
# =====================================================================
# Prepara las carpetas escribibles antes de arrancar Apache.
#
# CapRover monta los volumenes persistentes como root; sin este ajuste
# Apache (www-data) no podria guardar los documentos subidos ni los
# contadores del limitador de intentos.
# =====================================================================
set -e

for carpeta in /var/www/html/uploads/altas /var/www/html/data; do
    mkdir -p "$carpeta"
    chown -R www-data:www-data "$carpeta" 2>/dev/null || true
    chmod 750 "$carpeta" 2>/dev/null || true
done

# Aviso temprano y claro si falta la configuracion de la base de datos.
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "[altas] AVISO: faltan variables de entorno DB_HOST / DB_NAME / DB_USER." >&2
    echo "[altas] Configurelas en CapRover > App Configs > Environmental Variables." >&2
fi

exec "$@"
