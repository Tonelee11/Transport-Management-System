FROM php:8.2-fpm-alpine

# Install Nginx and required extensions
RUN apk add --no-cache \
    nginx \
    libcurl \
    curl-dev \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli curl

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Create directory for Nginx to store temp files
RUN mkdir -p /run/nginx

# Correct permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/uploads

# Expose port 80
EXPOSE 80

# Start PHP-FPM and Nginx
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
