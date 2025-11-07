# LLM Trace Cleaner

Plugin de WordPress que elimina automáticamente atributos de rastreo de herramientas LLM (ChatGPT, Claude, Bard, etc.) del contenido HTML de entradas y páginas.

## 📋 Descripción

**LLM Trace Cleaner** es un plugin diseñado para limpiar el contenido HTML de tu sitio WordPress eliminando todos los atributos de rastreo que las herramientas de inteligencia artificial (LLM) agregan al contenido cuando se copia y pega desde ellas.

### ¿Por qué usar este plugin?

Cuando copias contenido desde herramientas como ChatGPT, Claude o Bard, estos servicios agregan atributos HTML ocultos para rastrear el contenido. Estos atributos:
- Aumentan el tamaño del HTML
- Pueden afectar el rendimiento
- No son necesarios para el funcionamiento del sitio
- Pueden contener información sensible

Este plugin elimina automáticamente todos estos atributos, manteniendo tu contenido limpio y optimizado.

## ✨ Características

- ✅ **Limpieza automática**: Opción para limpiar automáticamente el contenido al guardar entradas/páginas
- 🧹 **Limpieza manual**: Botón para escanear y limpiar todo el contenido existente
- 📊 **Sistema de logging**: Registro completo de todas las acciones realizadas
- ⚡ **Procesamiento optimizado**: Sistema de lotes para evitar timeouts en sitios grandes
- 📈 **Barra de progreso**: Visualización en tiempo real del progreso de limpieza
- 🔒 **Seguro**: Verificación de permisos y protección con nonces
- 🎯 **Preciso**: Usa DOMDocument para un parsing robusto del HTML

## 🎯 Atributos eliminados

El plugin elimina los siguientes atributos cuando aparecen en el HTML:

- `data-start`
- `data-end`
- `data-is-last-node`
- `data-is-only-node`
- `data-llm`
- `data-pm-slice`
- `data-llm-id`
- `data-llm-trace`
- `data-original-text`
- `data-source-text`
- `data-highlight`
- `data-entity`
- `data-mention`
- Cualquier atributo `id` cuyo valor empiece por `model-response-message-contentr_`

## 📦 Requisitos

- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **Extensiones PHP**: `DOMDocument` (recomendado, pero no obligatorio)

## 🚀 Instalación

### Instalación manual

1. Descarga o clona este repositorio
2. Sube la carpeta `llm-trace-cleaner` al directorio `/wp-content/plugins/` de tu instalación de WordPress
3. Activa el plugin a través del menú 'Plugins' en WordPress
4. Ve a **Herramientas > LLM Trace Cleaner** para configurar el plugin

### Instalación desde ZIP

1. Descarga el archivo ZIP del repositorio
2. En WordPress, ve a **Plugins > Añadir nuevo**
3. Haz clic en **Subir plugin**
4. Selecciona el archivo ZIP y haz clic en **Instalar ahora**
5. Activa el plugin

## 📖 Uso

### Configuración inicial

1. Ve a **Herramientas > LLM Trace Cleaner** en el panel de administración de WordPress
2. En la sección **Configuración**, activa o desactiva la limpieza automática según tus necesidades
3. Guarda los cambios

### Limpieza automática

Si activas la limpieza automática:
- El contenido se limpiará automáticamente cada vez que guardes una entrada o página
- Los cambios se registrarán en el log del plugin
- No necesitas hacer nada más

### Limpieza manual

Para limpiar todo el contenido existente:

1. Ve a **Herramientas > LLM Trace Cleaner**
2. En la sección **Limpieza manual**, haz clic en **Escanear y limpiar contenido ahora**
3. El proceso se ejecutará en lotes pequeños para evitar sobrecargar el servidor
4. Observa el progreso en la barra de progreso
5. Al finalizar, verás un resumen con:
   - Número de posts analizados
   - Número de posts modificados
   - Detalle de atributos eliminados por tipo

### Ver el log

El plugin mantiene un registro de todas las acciones realizadas:

1. Ve a **Herramientas > LLM Trace Cleaner**
2. En la sección **Registro de actividad**, verás las últimas 50 acciones
3. Puedes vaciar el log haciendo clic en **Vaciar log**

## 🏗️ Estructura del plugin

```
llm-trace-cleaner/
├── llm-trace-cleaner.php          # Archivo principal
├── includes/
│   ├── class-llm-trace-cleaner-activator.php    # Activación/desactivación
│   ├── class-llm-trace-cleaner-cleaner.php      # Lógica de limpieza HTML
│   ├── class-llm-trace-cleaner-logger.php       # Sistema de logging
│   └── class-llm-trace-cleaner-admin.php        # Interfaz de administración
└── README.md
```

## 🔧 Desarrollo

### Tecnologías utilizadas

- **PHP**: 7.4+
- **WordPress**: API de WordPress
- **JavaScript**: jQuery (incluido en WordPress)
- **Base de datos**: Tabla personalizada para logs

### Hooks y filtros

El plugin utiliza los siguientes hooks de WordPress:

- `save_post`: Para limpieza automática
- `admin_menu`: Para agregar página de administración
- `admin_init`: Para manejar formularios
- `wp_ajax_*`: Para procesamiento AJAX

### Base de datos

El plugin crea una tabla personalizada al activarse:

- **Tabla**: `{prefix}llm_trace_cleaner_logs`
- **Campos**: id, datetime, action_type, post_id, post_title, details

## ⚙️ Configuración avanzada

### Tamaño de lote

Por defecto, el plugin procesa 10 posts por lote. Si necesitas ajustar esto, puedes modificar la variable `$batch_size` en el método `ajax_process_batch()` de la clase `LLM_Trace_Cleaner_Admin`.

### Tiempo de ejecución

Cada lote tiene un tiempo máximo de ejecución de 60 segundos. Esto se puede ajustar modificando `@set_time_limit(60)` en el mismo método.

## 🐛 Solución de problemas

### El proceso se detiene o da timeout

- El plugin está diseñado para procesar en lotes pequeños
- Si aún tienes problemas, reduce el tamaño del lote en el código
- Asegúrate de que tu servidor tenga suficiente memoria PHP

### No se eliminan los atributos

- Verifica que los atributos estén en la lista de atributos a eliminar
- Comprueba que el contenido tenga realmente esos atributos
- Revisa el log para ver si se registraron cambios

### Error al activar el plugin

- Verifica que tengas permisos para crear tablas en la base de datos
- Comprueba que PHP tenga la extensión `DOMDocument` (aunque no es obligatoria)
- Revisa los logs de error de WordPress

## 📝 Changelog

### 1.0.0
- Versión inicial
- Limpieza automática al guardar
- Limpieza manual con procesamiento por lotes
- Sistema de logging completo
- Interfaz de administración
- Barra de progreso en tiempo real

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz un fork del proyecto
2. Crea una rama para tu característica (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

```
Copyright (C) 2024

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 👤 Autor

**Yago Vázquez Gómez (Yaggoseo)**

- Website: (https://yaggoseo.com)
- GitHub: (https://github.com/yaggoSEO)

## 🙏 Agradecimientos

- WordPress por su excelente API
- La comunidad de desarrolladores de WordPress

## 📞 Soporte

Si encuentras algún problema o tienes sugerencias:

1. Abre un [issue](https://github.com/yaggoSEO/llm-trace-cleaner/issues)
2. Describe el problema detalladamente
3. Incluye información sobre tu entorno (versión de WordPress, PHP, etc.)

---

⭐ Si este plugin te resulta útil, considera darle una estrella en GitHub.

## 📌 Notas

- El plugin está optimizado para procesar grandes cantidades de contenido sin sobrecargar el servidor
- El sistema de logging ayuda a rastrear todos los cambios realizados
- La limpieza automática solo se ejecuta en posts y páginas publicados
- El plugin es compatible con la mayoría de temas y plugins de WordPress


