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

# Ensure write permissions on data directory and files
echo "Ensuring write permissions..."
chmod 775 /config/data 2>/dev/null || true
chmod 664 /config/data/*.db 2>/dev/null || true
chmod 664 /config/data/*.php 2>/dev/null || true

# Execute supervisord
exec "$@"
