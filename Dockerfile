FROM php:8.2-apache

# ---- System dependencies ----
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ---- PHP extensions ----
RUN docker-php-ext-install pdo pdo_sqlite

# ---- Enable Apache mod_rewrite ----
RUN a2enmod rewrite

# ---- Point Apache's document root at /var/www/html/public ----
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/

# ---- Permissions: web root readable, database dir (OUTSIDE public/) writable ----
RUN mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/database

# ---- Render.com assigns $PORT at runtime; rewrite Apache's listen port on boot ----
RUN echo '#!/bin/bash\n\
PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf\n\
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf\n\
exec apache2-foreground\n' > /usr/local/bin/start-apache.sh \
    && chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80
CMD ["/usr/local/bin/start-apache.sh"]
