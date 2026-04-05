FROM php:8.2-apache

# Install PostgreSQL PDO driver and cURL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for the front-controller routing
RUN a2enmod rewrite

# Use our custom VirtualHost that points DocumentRoot to /public
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy application source into the container
COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 80
