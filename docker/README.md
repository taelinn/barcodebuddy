# Docker Configuration for BarcodeBuddy

This directory contains the Docker configuration files for running BarcodeBuddy in a containerized environment.

## Files

- **nginx.conf** - Main NGINX configuration
- **default.conf** - NGINX virtual host configuration with API caching disabled
- **supervisord.conf** - Supervisor configuration to run PHP-FPM, NGINX, and WebSocket server

## Architecture

The Docker container runs:
1. **NGINX** - Web server on port 80
2. **PHP-FPM 8.2** - PHP processor
3. **WebSocket Server** - Real-time updates (port 47631, internal only)

All managed by **Supervisor** to ensure services stay running.

## Key Features

- ✅ Single container with all services
- ✅ Persistent data volume for SQLite database
- ✅ WebSocket support for real-time UI updates
- ✅ API caching disabled (important for reverse proxies)
- ✅ PHP 8.2 with all required extensions
- ✅ Alpine Linux for small image size

## Usage

See main repository README.md for deployment instructions.
