FROM php:8.2-fpm-alpine

# Install build dependencies
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev

# Install runtime dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    curl \
    wget \
    git \
    libpng \
    libjpeg-turbo \
    freetype

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        mysqli \
        mbstring \
        gd \
        sockets \
    && apk del --purge .build-deps

# Create application directory
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html

# Copy application files
COPY --chown=www-data:www-data . /var/www/html/

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Create volume for persistent data
VOLUME ["/var/www/html/data"]

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
