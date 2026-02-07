FROM alpine:3.18

LABEL maintainer="BarcodeBuddy"

# Install packages
RUN apk add --no-cache \
    bash \
    ca-certificates \
    curl \
    nano \
    nginx \
    php81 \
    php81-curl \
    php81-fileinfo \
    php81-fpm \
    php81-gettext \
    php81-json \
    php81-mbstring \
    php81-openssl \
    php81-pdo \
    php81-pdo_sqlite \
    php81-redis \
    php81-session \
    php81-simplexml \
    php81-sockets \
    php81-sqlite3 \
    php81-xml \
    php81-xmlwriter \
    php81-zlib \
    redis \
    shadow \
    supervisor \
    tzdata

# Configure PHP-FPM
RUN echo 'fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;' >> \
    /etc/nginx/fastcgi_params && \
    rm -f /etc/nginx/http.d/default.conf && \
    sed -i 's/pm.max_children = 5/pm.max_children = 20/g' /etc/php81/php-fpm.d/www.conf && \
    ln -sf /usr/bin/php81 /usr/bin/php

# Create application directories and user
RUN mkdir -p /app/bbuddy /config /data && \
    adduser -D -h /config -s /bin/false barcodebuddy && \
    chown -R barcodebuddy:barcodebuddy /app/bbuddy /config /data && \
    ln -s /config /data

# Copy application files
COPY --chown=barcodebuddy:barcodebuddy . /app/bbuddy/

# Set Docker flag in config
RUN sed -i 's/[[:blank:]]*const[[:blank:]]*IS_DOCKER[[:blank:]]*=[[:blank:]]*false;/const IS_DOCKER = true;/g' \
    /app/bbuddy/config-dist.php && \
    sed -i 's/const DEFAULT_USE_REDIS =.*/const DEFAULT_USE_REDIS = "1";/g' \
    /app/bbuddy/incl/db.inc.php

# Copy NGINX and supervisor configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Set working directory
WORKDIR /app/bbuddy

# Expose ports
EXPOSE 80

# Create volume for persistent data
VOLUME /config

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
