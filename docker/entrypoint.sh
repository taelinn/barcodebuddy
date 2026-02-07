#!/bin/bash
set -e

# Default PUID/PGID if not set
PUID=${PUID:-1000}
PGID=${PGID:-1000}

echo "Starting BarcodeBuddy with PUID=${PUID} and PGID=${PGID}"

# Update barcodebuddy user/group IDs
groupmod -o -g "$PGID" barcodebuddy
usermod -o -u "$PUID" barcodebuddy

# Fix ownership of directories
echo "Fixing permissions..."
chown -R barcodebuddy:barcodebuddy /config /app/bbuddy

# Execute supervisord
exec "$@"
