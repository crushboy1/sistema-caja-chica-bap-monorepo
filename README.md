# Sistema de Gestión de Fondos de Efectivo (SGFE-BAP)

Este repositorio contiene el código fuente del Sistema de Gestión de Fondos de Efectivo, desarrollado para el **Banco de Alimentos Perú (BAP)**.  
El proyecto está construido como un **monorepositorio** que incluye un backend en **Laravel** y un frontend en **Vue.js**, orquestado mediante **Docker**.

---

## Pre-requisitos

Para poder ejecutar este proyecto localmente, necesitarás tener instalado el siguiente software:

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)

---

## Guía de Instalación Local

Sigue estos pasos para levantar el entorno de desarrollo completo en tu máquina.

### 1. Clonar el Repositorio

```bash
git clone [URL_DE_TU_REPOSITORIO_EN_GITHUB]
cd sistema-gestion-fondos-efectivo-bap-monorepo
```

### 2. Configurar las Variables de Entorno

Este proyecto utiliza dos archivos `.env` para gestionar las variables de entorno: uno en la raíz para Docker y otro para la aplicación Laravel.

#### Paso 2.1: Archivo `.env` para Docker (Raíz del proyecto)

```bash
cp .env.example .env
```

Edita el archivo `.env` y completa las variables necesarias, como `DB_PASSWORD`. Estos valores serán proporcionados por el líder del proyecto a través de un canal seguro.

#### Paso 2.2: Archivo `.env` para Laravel

```bash
cd sistema-caja-chica-bap
cp .env.example .env
cd ..
```

> **Nota:** No es necesario editar este archivo manualmente, ya que el siguiente paso lo configurará automáticamente.

---

### 3. Levantar los Contenedores con Docker Compose

Desde la raíz del monorepositorio:

```bash
docker-compose up -d --build
```

> **Nota:** Este proceso puede tardar varios minutos la primera vez.

---

### 4. Configuración Final de la Aplicación Laravel

Una vez que los contenedores estén corriendo, ejecuta los siguientes comandos dentro del contenedor de la aplicación:

#### Generar la Clave de la Aplicación

```bash
docker-compose exec app php artisan key:generate
```

#### Ejecutar las Migraciones y Seeders

```bash
docker-compose exec app php artisan migrate:fresh --seed
```

Esto creará la estructura de la base de datos y la poblará con datos de prueba.

#### Regenerar archivos ignorados (.gitignore)

Si al clonar el repositorio faltan algunos archivos o carpetas necesarias por estar ignoradas (como archivos `.key`, `.log` o carpetas de Laravel como `storage/pail`), puedes ejecutar los siguientes comandos dentro del contenedor para asegurarte de que todo esté en orden:

```bash
docker-compose exec app php artisan storage:link
docker-compose exec app chmod -R 775 storage
```

---

## 5. ¡Listo! Acceder al Sistema

Ahora puedes acceder al sistema desde tu navegador:

- **Frontend (Vue.js):** [http://localhost:3000](http://localhost:3000)
- **Backend (Laravel API):** [http://localhost:8080](http://localhost:8080)

---

## Credenciales de Acceso de Prueba

Puedes utilizar los siguientes usuarios para probar los diferentes roles del sistema:

### Rol Gerente General
- **Usuario:** `carlos.lopez@bap.com`
- **Contraseña:** `password`

### Rol Administrador
- **Usuario:** `admin@bap.com`
- **Contraseña:** `password`

### Rol Jefe de Administración
- **Usuario:** `maria.gomez@bap.com`
- **Contraseña:** `password`

### Rol Jefe de Área
- **Usuario:** `juan.perez@bap.com`
- **Contraseña:** `password`
