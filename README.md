# Plugin Certificados Antecore

Sistema de gestión y aprobación de certificados de Gas Licuado de Petróleo (GLP) para WordPress.

## 🎯 Descripción

Este plugin permite gestionar certificados de conformidad para instalaciones de GLP, incluyendo plantas almacenadoras, tanques estacionarios, plantas de envasado, depósitos y expendios de cilindros.

## ✨ Características

### Para Colaboradores (Rol: Contributor)
- ✅ Solicitar certificados individuales
- ✅ **NUEVO**: Carga masiva de certificados desde Excel/CSV
- ✅ Editar certificados pendientes
- ✅ Ver historial de certificados
- ✅ Descargar PDFs de certificados aprobados

### Para Administradores
- ✅ Aprobar/rechazar certificados
- ✅ Configurar notificaciones por email
- ✅ Ver estadísticas de certificados
- ✅ Exportar datos

## 🚀 Instalación

1. Sube la carpeta `certificado` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración de WordPress
3. Configura las notificaciones en **Certificados → Configuración de Notificaciones**

## 📋 Uso

### Carga Masiva de Certificados (NUEVA FUNCIONALIDAD)

1. Ve a **Mis Certificados** en el panel de colaborador
2. Selecciona la pestaña **"Carga Masiva (Excel)"**
3. Descarga la plantilla Excel/CSV
4. Llena la plantilla con los datos de los certificados
5. Sube el archivo completado
6. Revisa los resultados del procesamiento

### Plantilla Excel/CSV

La plantilla incluye las siguientes columnas:

| Columna | Descripción | Ejemplo |
|---------|-------------|---------|
| NOMBRE_INSTALACION | Nombre de la instalación | "Estación de Servicio ABC" |
| DIRECCION_INSTALACION | Dirección completa | "Calle 123 #45-67, Bogotá" |
| RAZON_SOCIAL | Razón social de la empresa | "Servicios ABC S.A.S." |
| NIT | Número de identificación tributaria | "900123456-1" |
| CAPACIDAD_ALMACENAMIENTO | Capacidad en galones | "10000" |
| NUMERO_TANQUES | Cantidad de tanques | "5" |
| TIPO_CERTIFICADO | Tipo de certificado | "PAGLP" |
| NUMERO_CERTIFICADO | Número del certificado | "001" |
| FECHA_APROBACION | Fecha en formato DD/MM/YYYY | "15/12/2024" |

### Tipos de Certificado Válidos
- **PAGLP**: Planta de Almacenamiento de GLP
- **TEGLP**: Tanque de Almacenamiento de GLP
- **PEGLP**: Planta de Envasado de GLP
- **DEGLP**: Distribuidora de GLP
- **PVGLP**: Punto de Venta de GLP

## 🔧 Configuración

### Notificaciones por Email
1. Ve a **Certificados → Configuración de Notificaciones**
2. Configura los emails que recibirán notificaciones
3. Personaliza los mensajes de notificación

### Permisos de Usuario
- **Contributor**: Puede solicitar y editar sus certificados
- **Administrator**: Puede aprobar/rechazar certificados y configurar el sistema

## 📁 Estructura del Plugin

```
certificado/
├── admin/
│   ├── formulario-colaborador.php    # Formulario con pestañas (individual/masivo)
│   ├── aprobacion-certificados.php   # Panel de aprobación
│   └── configuracion-notificaciones.php
├── includes/
│   ├── funciones-bd.php              # Funciones de base de datos
│   ├── funciones-pdf.php             # Generación de PDFs
│   └── funciones-excel.php           # Procesamiento de Excel/CSV
├── public/
│   ├── css/
│   ├── js/
│   └── template-certificados-public.php
├── templates/
│   └── plantilla-certificado.html
├── images/
└── certificados-personalizados.php   # Archivo principal
```

## 🛠️ Requisitos del Sistema

- WordPress 5.0 o superior
- PHP 7.4 o superior
- MySQL 5.6 o superior
- Librería TCPDF (incluida)

## 📝 Formatos de Archivo Soportados

### Carga Masiva
- **CSV** (.csv) - Recomendado
- **Excel** (.xlsx, .xls) - Limitado (requiere PhpSpreadsheet para funcionalidad completa)

### Límites
- Tamaño máximo de archivo: 5MB
- Codificación: UTF-8
- Separador CSV: Coma (,)

## 🔍 Validaciones

El sistema valida automáticamente:
- ✅ Campos obligatorios
- ✅ Tipos de certificado válidos
- ✅ NIT únicos (no duplicados)
- ✅ Formatos de fecha correctos
- ✅ Valores numéricos válidos
- ✅ Fechas no futuras

## 🚨 Manejo de Errores

El sistema reporta:
- **Errores de validación**: Por cada fila con problemas
- **Archivos inválidos**: Formato o tamaño incorrecto
- **Duplicados**: NITs ya existentes
- **Resultados**: Resumen de procesamiento exitoso

## 🔄 Actualizaciones

### v2.0.0 - Carga Masiva
- ✅ Nueva funcionalidad de carga masiva desde Excel/CSV
- ✅ Sistema de pestañas en el formulario
- ✅ Validaciones mejoradas
- ✅ Plantilla descargable
- ✅ Procesamiento por lotes

### v1.0.0 - Versión Base
- ✅ Gestión básica de certificados
- ✅ Sistema de aprobación
- ✅ Generación de PDFs
- ✅ Notificaciones por email

## 🤝 Soporte

Para soporte técnico o reportar bugs, contacta al administrador del sistema.

## 📄 Licencia

Este plugin está desarrollado específicamente para Antecore y su uso está restringido a la organización.

---

**Desarrollado para Antecore** 🏢  
Sistema de Certificados GLP v2.0.0
