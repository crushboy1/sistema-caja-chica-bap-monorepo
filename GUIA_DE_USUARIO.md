# Guía de Usuario – Sistema de Gestión de Fondos de Efectivo (SGFE-BAP)

## 1. Introducción

Bienvenido a la guía de usuario del Sistema de Gestión de Fondos de Efectivo (SGFE-BAP). Este documento proporciona instrucciones detalladas sobre cómo utilizar el sistema, adaptadas a los diferentes roles de usuario.

El SGFE-BAP es una aplicación web diseñada para gestionar las solicitudes, aprobaciones y rendiciones de fondos de caja chica para el personal del Banco de Alimentos Perú (BAP).

## 2. Acceso al Sistema

Para acceder al sistema, abre tu navegador web y dirígete a la siguiente URL:

- **URL de Producción:** [http://tu-dominio.com](http://tu-dominio.com)
- **URL de Desarrollo Local:** [http://localhost:3000](http://localhost:3000)

Aparecerá la pantalla de inicio de sesión.

### Inicio de Sesión

Para ingresar, utiliza las credenciales (correo electrónico y contraseña) que te fueron asignadas.

## 3. Roles de Usuario

El sistema cuenta con varios roles, cada uno con diferentes niveles de acceso y permisos. A continuación, se describen las responsabilidades de cada rol.

### 3.1. Rol Jefe de Área

- **Descripción:** Responsable de un área o departamento específico.
- **Funcionalidades Principales:**
    - **Crear Solicitudes de Fondos:** Solicitar dinero para gastos operativos de su área.
    - **Registrar Gastos:** Documentar los gastos realizados con los fondos asignados.
    - **Ver Historial:** Consultar el estado de sus solicitudes y los gastos asociados.

### 3.2. Rol Jefe de Administración

- **Descripción:** Supervisa las operaciones administrativas y financieras.
- **Funcionalidades Principales:**
    - **Revisar y Aprobar Solicitudes:** Evaluar y aprobar o rechazar las solicitudes de fondos de los Jefes de Área.
    - **Gestionar Fondos:** Administrar la asignación de fondos a las diferentes áreas.
    - **Generar Reportes:** Crear informes sobre los gastos y el estado de los fondos.

### 3.3. Rol Administrador

- **Descripción:** Gestiona la configuración general del sistema.
- **Funcionalidades Principales:**
    - **Gestión de Usuarios:** Crear, editar y eliminar cuentas de usuario.
    - **Gestión de Roles y Permisos:** Asignar roles y permisos a los usuarios.
    - **Configuración del Sistema:** Administrar catálogos como áreas, proyectos y cuentas contables.

### 3.4. Rol Gerente General

- **Descripción:** Máxima autoridad en la organización.
- **Funcionalidades Principales:**
    - **Dashboard de Alto Nivel:** Visualizar un resumen ejecutivo del estado de los fondos y los gastos.
    - **Aprobaciones Especiales:** Autorizar solicitudes que excedan ciertos límites o requieran una aprobación de nivel superior.
    - **Consulta de Reportes:** Acceder a todos los reportes generados por el sistema.

## 4. Funcionalidades Principales

### 4.1. Dashboard

Al iniciar sesión, verás un panel de control con un resumen de la información más relevante según tu rol. Esto puede incluir:
- Estado de tus solicitudes de fondos.
- Gráficos de gastos por categoría.
- Alertas y notificaciones importantes.

### 4.2. Solicitudes de Fondos

Este módulo te permite gestionar todo el ciclo de vida de una solicitud de fondos.

#### Crear una Solicitud (Jefe de Área)

1.  Ve a la sección **"Solicitudes"** y haz clic en **"Nueva Solicitud"**.
2.  Completa el formulario con los detalles requeridos, como el motivo de la solicitud y el monto.
3.  Envía la solicitud para su revisión.

#### Aprobar/Rechazar una Solicitud (Jefe de Administración)

1.  En la sección **"Solicitudes Pendientes"**, selecciona la solicitud que deseas revisar.
2.  Evalúa los detalles y haz clic en **"Aprobar"** o **"Rechazar"**.
3.  Puedes añadir comentarios para justificar tu decisión.

### 4.3. Gestión de Gastos

Una vez que una solicitud de fondos es aprobada y el dinero es entregado, debes registrar los gastos correspondientes.

#### Registrar un Gasto (Jefe de Área)

1.  Busca la solicitud de fondos aprobada en la sección **"Mis Solicitudes"**.
2.  Haz clic en **"Registrar Gasto"**.
3.  Completa el formulario adjuntando los comprobantes (facturas, boletas, etc.) y los detalles del gasto.

### 4.4. Administración del Sistema (Rol Administrador)

El módulo de **"Administración"** permite configurar los componentes clave del sistema.

- **Gestión de Usuarios:** Crea nuevas cuentas de usuario y asigna roles.
- **Gestión de Áreas y Proyectos:** Define las áreas y proyectos de la organización para asociarlos a las solicitudes de fondos.
- **Gestión de Cuentas Contables:** Administra el catálogo de cuentas contables utilizadas para clasificar los gastos.

## 5. Reportes

El sistema permite generar diversos reportes para el control y la toma de decisiones.

- **Reporte de Gastos por Área/Proyecto:** Muestra un desglose de los gastos por cada área o proyecto.
- **Reporte de Estado de Fondos:** Presenta un resumen del flujo de efectivo y los saldos disponibles.
- **Reporte de Rendiciones:** Consolida la información de los gastos rendidos y pendientes.

## 6. Soporte

Si encuentras algún problema o tienes alguna pregunta, por favor, contacta al equipo de soporte a través de [correo_de_soporte@ejemplo.com](mailto:correo_de_soporte@ejemplo.com).
