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
    docker-php-ext-install pdo_mysql zip gd mbstring exif pcntl bcmath

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

# El COPY . . se hará mediante volúmenes en docker-compose
# Para el contenedor, nos ubicamos en la carpeta específica del proyecto Laravel
WORKDIR /var/www/html/sistema-caja-chica-bap

# Expone el puerto web
EXPOSE 80

# Comando por defecto para iniciar Apache
CMD ["apache2-foreground"]