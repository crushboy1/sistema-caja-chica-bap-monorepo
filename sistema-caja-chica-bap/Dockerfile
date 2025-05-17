# Usa PHP 8.2 con Apache
FROM php:8.2-apache

# Argumento para el entorno
ARG APP_ENV=local

# Instala dependencias del sistema necesarias
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    ca-certificates \
    gnupg \
    lsb-release \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Instala extensiones PHP necesarias para Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql zip gd

# Instala Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instala Node.js 20.x (LTS) y npm desde NodeSource
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm

# Habilita mod_rewrite de Apache (necesario para URLs limpias en Laravel)
RUN a2enmod rewrite

# Copia configuración personalizada de Apache
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Establece el directorio de trabajo
WORKDIR /var/www/html

# IMPORTANTE: Copia primero TODOS los archivos del proyecto
COPY . .



# Instala dependencias de PHP con la opción --ignore-platform-reqs
RUN composer install --ignore-platform-reqs

# Genera la clave de aplicación si no existe
#RUN php artisan key:generate --force

# Solución específica para problemas de Rollup/Vite en entorno Linux
RUN rm -rf node_modules package-lock.json && \
    npm install --no-optional && \
    npm rebuild && \
    npm install --platform=linux --arch=x64 @rollup/rollup-linux-x64-gnu

# Compila los assets con Vite
RUN npm run build

# Asegura que las carpetas necesarias tengan permisos adecuados
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Expone el puerto web
EXPOSE 80

# Comando por defecto para iniciar Apache
CMD ["apache2-foreground"]