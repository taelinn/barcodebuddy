# Docker Deployment Guide

This guide covers deploying BarcodeBuddy using Docker.

## Quick Start

1. **Clone the repository:**
   ```bash
   git clone https://github.com/taelinn/barcodebuddy.git
   cd barcodebuddy
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   nano .env  # Edit with your Grocy URL and API key
   ```

3. **Build and run:**
   ```bash
   docker-compose up -d
   ```

4. **Access BarcodeBuddy:**
   - Open http://localhost:9280
   - Or http://your-server-ip:9280

## Configuration

### Environment Variables

Edit `.env` file with your settings:

```env
# Grocy Configuration (Required)
GROCY_API_URL=http://your-grocy-instance:9283/api
GROCY_API_KEY=your_grocy_api_key

# Timezone (Optional)
TZ=America/New_York

# Port (Optional, default 9280)
BBUDDY_PORT=9280
```

### Volume Mounts

The `./data` directory is mounted as a volume for persistent storage:
- SQLite databases (`barcodebuddy.db`, `users.db`)
- Configuration file (`config.php`)

## Docker Commands

### Build from source:
```bash
docker-compose build
```

### Start services:
```bash
docker-compose up -d
```

### Stop services:
```bash
docker-compose down
```

### View logs:
```bash
docker-compose logs -f
```

### Restart after code changes:
```bash
git pull
docker-compose build --no-cache
docker-compose up -d
```

### Access container shell:
```bash
docker exec -it barcodebuddy sh
```

## Architecture

The container includes:
- **NGINX** - Web server (port 80, mapped to 9280)
- **PHP-FPM 8.2** - PHP processor with required extensions
- **WebSocket Server** - Real-time UI updates (port 47631, internal only)
- **Supervisor** - Process manager

## Troubleshooting

### Check container status:
```bash
docker-compose ps
```

### Check logs:
```bash
docker-compose logs barcodebuddy
```

### Check WebSocket server:
```bash
docker exec barcodebuddy ps aux | grep wsserver
```

### Rebuild after API changes:
```bash
docker-compose build --no-cache
docker-compose up -d
```

### Reset database (WARNING: Deletes all data):
```bash
docker-compose down
rm -rf data/
docker-compose up -d
```

## Reverse Proxy Configuration

### Nginx

```nginx
location /barcodebuddy/ {
    proxy_pass http://localhost:9280/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Important: Disable caching for API endpoints
    proxy_no_cache 1;
    proxy_cache_bypass 1;
}
```

### Traefik

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.barcodebuddy.rule=Host(`barcodebuddy.example.com`)"
  - "traefik.http.services.barcodebuddy.loadbalancer.server.port=80"
```

## Production Deployment

For production, consider:

1. **Use environment variables** instead of `.env` file
2. **Enable HTTPS** via reverse proxy
3. **Set proper timezone** in `.env`
4. **Backup data directory** regularly
5. **Monitor logs** for errors

## Updating

To update to the latest version:

```bash
cd barcodebuddy
git pull
docker-compose build --no-cache
docker-compose up -d
```

Your data in `./data` directory will be preserved.

## Port Conflicts

If port 9280 is already in use, change it in `docker-compose.yml`:

```yaml
ports:
  - "8080:80"  # Use port 8080 instead
```

## Network Mode

By default, BarcodeBuddy runs on its own bridge network. To use host network mode:

```yaml
services:
  barcodebuddy:
    network_mode: host
```

## Support

For issues specific to Docker deployment, check:
1. Container logs: `docker-compose logs`
2. NGINX logs: `docker exec barcodebuddy cat /var/log/nginx/error.log`
3. PHP-FPM logs: `docker exec barcodebuddy cat /var/log/php8/error.log`
