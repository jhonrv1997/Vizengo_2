# VIZENGO - Sistema de Gestión de Pedidos

Sistema completo de gestión de pedidos para tienda de ropa deportiva por pedidos.

## 📋 Requisitos del Sistema

- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior
- **Apache**: con mod_rewrite habilitado
- **Extensiones PHP**: PDO, PDO_MySQL, json, mbstring

## 🚀 Instalación

### Paso 1: Descargar archivos

Descarga todos los archivos del proyecto y súbelos a tu servidor Cpanel en la carpeta `public_html` o un subdirectorio.

### Paso 2: Crear la base de datos

1. Accede a **phpMyAdmin** desde tu panel de control (Cpanel)
2. Crea una nueva base de datos llamada `vizengo_db`
3. Selecciona la base de datos creada
4. Importa el archivo `database/vizengo_database.sql`

### Paso 3: Configurar la conexión

Edita el archivo `config.php` con los datos de tu base de datos:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vizengo_db');        // Nombre de tu base de datos
define('DB_USER', 'tu_usuario_db');      // Usuario de la base de datos
define('DB_PASS', 'tu_password_db');     // Contraseña de la base de datos
```

### Paso 4: Configurar la URL del sitio

En el mismo archivo `config.php`, actualiza:

```php
define('SITE_URL', 'https://tudominio.com'); // Tu dominio
define('DEV_MODE', false); // Cambiar a false en producción
```

### Paso 5: Configurar permisos

Asegúrate de que la carpeta `uploads/` tenga permisos de escritura:

```bash
chmod -R 755 uploads/
```

### Paso 6: Acceder al sistema

Abre tu navegador y accede a tu dominio. Serás redirigido a la página de login.

## 👥 Usuarios por Defecto

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| luis | password | Vendedor |
| karina | password | Vendedor |
| carolina | password | Diseñador |
| erick | password | Diseñador |
| admin | password | Administrador |

⚠️ **Importante**: Cambia las contraseñas después del primer acceso.

## 📁 Estructura del Proyecto

```
vizengo/
├── api/                    # Endpoints de la API
│   ├── auth.php           # Autenticación
│   ├── pedidos.php        # Gestión de pedidos
│   └── usuarios.php       # Gestión de usuarios
├── assets/
│   ├── css/style.css      # Estilos principales
│   └── js/app.js          # JavaScript principal
├── database/
│   └── vizengo_database.sql  # Script de base de datos
├── includes/
│   └── sidebar.php        # Componente sidebar
├── uploads/               # Archivos subidos
├── config.php             # Configuración principal
├── index.php              # Página de login
├── dashboard.php          # Panel principal
├── lista-pedidos.php      # Lista de pedidos
├── ingreso-pedido.php     # Nuevo pedido
├── registro-integrantes.php # Registro de integrantes
├── diseno.php             # Subir diseños
├── planchado.php          # Registro de planchado
├── costura.php            # Registro de costura
├── entrega.php            # Registro de entrega
├── usuarios.php           # Gestión de usuarios
├── seguimiento.php        # Seguimiento de pedidos
└── .htaccess              # Configuración Apache
```

## 🔐 Roles del Sistema

### Vendedor
- Registrar nuevos pedidos (contratos)
- Registrar integrantes del equipo
- Gestionar entregas
- Ver sus pedidos asignados

### Diseñador
- Subir diseños finales
- Registrar planchado
- Registrar costura
- Ver pedidos pendientes de diseño

### Administrador
- Acceso total al sistema
- Gestionar usuarios
- Ver todos los pedidos
- Acceder a todas las funcionalidades

## 📊 Flujo de Trabajo (6 Etapas)

1. **Contrato** - El vendedor registra el pedido con los datos del cliente
2. **Integrantes** - Se registran los integrantes con tallas y números
3. **Diseño** - El diseñador sube los diseños finales
4. **Planchado** - Se registra el trabajo de planchado
5. **Costura** - Se registra el trabajo de costura
6. **Entrega** - El vendedor registra la entrega al cliente

## 🛠️ Tecnologías Utilizadas

- **Backend**: PHP 7.4+ con PDO
- **Base de datos**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6
- **Fuentes**: Google Fonts (Barlow, Barlow Condensed)

## 📱 Características

- ✅ Sistema de autenticación con roles
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Pipeline visual de 6 etapas
- ✅ Gestión de pedidos completa
- ✅ Registro de integrantes con tallas
- ✅ Subida de diseños (imágenes)
- ✅ Registro de planchado y costura
- ✅ Control de entregas
- ✅ Exportación de datos
- ✅ Diseño responsive
- ✅ Alertas de pedidos urgentes

## 🔧 Mantenimiento

### Backup de la base de datos

Se recomienda hacer backup regular de la base de datos desde phpMyAdmin o mediante:

```bash
mysqldump -u usuario -p vizengo_db > backup_$(date +%Y%m%d).sql
```

### Logs del sistema

Los errores se registran en el log de PHP del servidor. Para habilitar más detalles en desarrollo:

```php
define('DEV_MODE', true);
```

## 📞 Soporte

Para soporte técnico, contacta al equipo de desarrollo.

---

**VIZENGO** - Sistema de Gestión de Pedidos para Ropa Deportiva
© 2025 - Todos los derechos reservados
