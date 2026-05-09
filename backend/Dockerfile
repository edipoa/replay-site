FROM php:8.2-apache

# mod_rewrite para o .htaccess funcionar
RUN a2enmod rewrite

# Ferramentas necessárias para o Composer baixar pacotes
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip git \
 && rm -rf /var/lib/apt/lists/*

# Extensão PDO MySQL
RUN docker-php-ext-install pdo_mysql

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# VirtualHost apontando para public/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Instala dependências primeiro (cache de layer)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copia o restante do código
COPY . .

EXPOSE 80
