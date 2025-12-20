# LLM Trace Cleaner

Plugin de WordPress que elimina automáticamente atributos de rastreo de herramientas LLM (ChatGPT, Claude, Gemini, etc.) del contenido HTML de entradas y páginas.

## 📋 Descripción

**LLM Trace Cleaner** es un plugin diseñado para limpiar el contenido HTML de tu sitio WordPress eliminando todos los atributos de rastreo que las herramientas de inteligencia artificial (LLM) agregan al contenido cuando se copia y pega desde ellas.

### ¿Por qué usar este plugin?

Cuando copias contenido desde herramientas como ChatGPT, Claude o Gemini, estos servicios agregan atributos HTML ocultos para rastrear el contenido. Estos atributos:
- Aumentan el tamaño del HTML
- Pueden afectar el rendimiento
- No son necesarios para el funcionamiento del sitio
- Pueden contener información sensible

Este plugin elimina automáticamente todos estos atributos, manteniendo tu contenido limpio y optimizado.

## ✨ Características

- ✅ **Limpieza automática**: Opción para limpiar automáticamente el contenido al guardar entradas/páginas
- 🧹 **Limpieza manual**: Botón para escanear y limpiar todo el contenido existente
- 📊 **Sistema de logging**: Registro completo de todas las acciones realizadas con detección inteligente de atributos eliminados
- ⚡ **Procesamiento optimizado**: Sistema de lotes para evitar timeouts en sitios grandes
- 📈 **Barra de progreso**: Visualización en tiempo real del progreso de limpieza
- 🔒 **Seguro**: Verificación de permisos y protección con nonces
- 🎯 **Preciso**: Usa DOMDocument para un parsing robusto del HTML
- 🚫 **Gestión de caché inteligente**: Desactiva y limpia automáticamente la caché durante el proceso de limpieza para evitar interferencias
- 🤖 **Detección de bots/LLMs**: Opción para desactivar caché cuando bots o herramientas LLM acceden al sitio
- 🐛 **Depuración integrada**: Pestaña dedicada para diagnosticar errores y problemas durante el procesamiento
- 📡 **Telemetría anónima (opt-in)**: Opción para compartir estadísticas anónimas con propósitos de investigación sobre LLMs y buscadores
- 🔄 **Actualizaciones automáticas**: Sistema de actualizaciones directas desde GitHub sin necesidad de descargar manualmente

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
- `data-offset-key`
- `data-message-id`
- `data-sender` / `data-role`
- `data-token-index`
- `data-model`
- `data-render-timestamp`
- `data-update-timestamp`
- `data-confidence`
- `data-temperature`
- `data-seed`
- `data-step`
- `data-lang`
- `data-format`
- `data-annotation`
- `data-reference`
- `data-version`
- `data-error`
- `data-stream-id`
- `data-chunk`
- `data-context-id`
- `data-user-id`
- `data-ui-state`
- Cualquier atributo `id` cuyo valor empiece por `model-response-message-contentr_`

### Referencias de contenido LLM eliminadas

El plugin también elimina referencias de contenido que algunos LLMs agregan al texto:

- `ContentReference [oaicite:=0](index=0)` y variaciones
- `[oaicite:0]`, `[oaicite:=1]`, etc.

### Parámetros UTM eliminados de enlaces

El plugin elimina parámetros UTM de los enlaces que algunos LLMs agregan automáticamente:

- `?utm_source=chatgpt.com`
- `?utm_medium=chat`
- `?utm_campaign=...`
- Y cualquier otro parámetro `utm_*`

### Caracteres Unicode invisibles eliminados

El plugin también elimina caracteres invisibles que suelen usarse para marcas, manipulación del renderizado o confusión visual. Algunos ejemplos:

- Zero Width Space (U+200B), ZWNJ (U+200C), ZWJ (U+200D)
- Zero Width No-Break Space / BOM (U+FEFF)
- Word Joiner (U+2060), Invisible Separator (U+2063), Invisible Plus (U+2064), Invisible Times (U+2062)
- Soft Hyphen (U+00AD)
- Marcas de direccionalidad y control bidi: LRM (U+200E), RLM (U+200F), LRE/RLE/PDF/LRO/RLO (U+202A–U+202E), aislantes (U+2066–U+2069)
- Mongolian Vowel Separator (U+180E)
- Tag Characters (U+E0000–U+E007F)
- Invisible Ideographic Space (U+3000)
- Object Replacement Character (U+FFFC)
- Variation Selectors (U+FE00–U+FE0F)

Estos caracteres se registran en el log con el prefijo “unicode: ...” para que puedas ver exactamente cuál fue eliminado.

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
- La caché se limpiará automáticamente después de cada modificación
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
2. En la sección **Registro de actividad**, verás las últimas 50 acciones (con paginación si hay más)
3. Solo se muestran los posts/páginas que tenían atributos de rastreo eliminados
4. El log muestra qué tipo de atributos se encontraron y eliminaron
5. Puedes vaciar el log haciendo clic en **Vaciar log**
6. Puedes descargar el archivo de log completo haciendo clic en **Descargar archivo de log**

### Gestión de caché

El plugin incluye un sistema inteligente de gestión de caché que:

- **Durante la limpieza**: Desactiva automáticamente la caché para evitar interferencias
- **Después de modificar posts**: Limpia la caché de cada post modificado
- **Al finalizar**: Limpia toda la caché del sitio para asegurar que los cambios se reflejen

**Compatibilidad con plugins de caché:**
- ✅ LiteSpeed Cache
- ✅ WP Rocket
- ✅ W3 Total Cache
- ✅ WP Super Cache
- ✅ NitroPack
- ✅ Cache Enabler
- ✅ Comet Cache
- ✅ WP Fastest Cache
- ✅ Autoptimize

**Desactivar caché para bots/LLMs:**

El plugin también puede desactivar la caché cuando detecta que bots o herramientas LLM acceden al sitio:

1. Ve a **Herramientas > LLM Trace Cleaner**
2. Activa la opción **Desactivar caché para bots/LLMs**
3. Selecciona los bots/LLMs que quieres detectar (ChatGPT, Claude, Bard, etc.)
4. Opcionalmente, agrega bots personalizados (uno por línea)
5. Guarda la configuración

Esto asegura que los bots y herramientas LLM siempre vean el contenido más reciente sin interferencias de la caché.

## 🏗️ Estructura del plugin

```
llm-trace-cleaner/
├── llm-trace-cleaner.php                        # Archivo principal
├── .env                                          # Token de GitHub (NO subir al repo)
├── env.example                                   # Plantilla para .env
├── CHANGELOG.md                                  # Historial de cambios
├── README.md                                     # Documentación
├── includes/
│   ├── class-llm-trace-cleaner-activator.php    # Activación/desactivación
│   ├── class-llm-trace-cleaner-cleaner.php      # Lógica de limpieza HTML
│   ├── class-llm-trace-cleaner-logger.php       # Sistema de logging
│   ├── class-llm-trace-cleaner-cache.php        # Gestión de caché
│   ├── class-llm-trace-cleaner-admin.php        # Interfaz de administración
│   ├── class-llm-trace-cleaner-env-loader.php   # Cargador de variables .env
│   └── class-llm-trace-cleaner-github-updater.php # Sistema de actualizaciones
└── llm-trace-cleaner.log                        # Archivo de log (generado)
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

Cada lote tiene un tiempo máximo de ejecución de 120 segundos. Esto se puede ajustar modificando `@set_time_limit(120)` en el mismo método.

## 🖥️ Requisitos del servidor recomendados

Para un funcionamiento óptimo del plugin, especialmente cuando se procesan grandes cantidades de contenido (más de 1000 entradas), se recomiendan los siguientes valores de configuración del servidor:

### PHP

- **Versión**: PHP 7.4 o superior (PHP 8.0+ recomendado)
- **memory_limit**: Mínimo 256MB (512MB recomendado para sitios grandes)
- **max_execution_time**: Mínimo 120 segundos (300 segundos recomendado)
- **post_max_size**: Mínimo 64MB
- **upload_max_filesize**: Mínimo 64MB

### Servidor web

- **Timeout de conexión**: Mínimo 150 segundos (recomendado 300 segundos)
- **Nginx**: `proxy_read_timeout 300s;`
- **Apache**: `Timeout 300` en la configuración

### Base de datos

- **MySQL/MariaDB**: Versión 5.7 o superior
- **Tiempo de conexión**: Mínimo 300 segundos
- **max_allowed_packet**: Mínimo 64MB

### WordPress

- **WP_MEMORY_LIMIT**: Mínimo 256M (definir en `wp-config.php`)
- **WP_MAX_MEMORY_LIMIT**: Mínimo 512M (definir en `wp-config.php`)

### Configuración en wp-config.php

Para optimizar el rendimiento, agrega estas líneas a tu archivo `wp-config.php`:

```php
// Aumentar límite de memoria
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Aumentar tiempo de ejecución
set_time_limit(300);
```

### Notas importantes

- Estos valores son especialmente importantes cuando se procesan más de 1000 entradas
- El plugin está diseñado para manejar timeouts automáticamente y continuar el proceso
- Si experimentas problemas de timeout, considera aumentar los valores según las recomendaciones
- Para sitios con más de 5000 entradas, se recomienda aumentar aún más los valores de memoria y tiempo de ejecución

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

### 1.6.1
- **Corrección crítica de persistencia de transients**:
  - Solucionado el problema donde el estado del proceso no se encontraba al iniciar la limpieza manual
  - Implementada persistencia directa en base de datos para evitar problemas con object cache (Redis, Memcached)
  - El sistema ahora guarda y lee los transients directamente desde la base de datos, evitando problemas de sincronización
  - Optimización: Ya no se guardan todos los IDs de posts en el transient (solo metadatos), recalculándolos cuando es necesario
  - Mejora en la confiabilidad del proceso de limpieza por lotes

### 1.6.0
- **Telemetría mejorada para estudios e investigación**:
  - Nuevos datos capturados: Content References y UTM Parameters (totales, tipos únicos y detalle por tipo)
  - Métricas de rendimiento: tiempo de procesamiento, posts por segundo, ratio de modificación
  - Métricas agregadas: total de items removidos, promedio de items por post modificado
  - Opciones de limpieza usadas: registro de qué opciones estaban activas durante la limpieza
  - Google Sheets mejorado: 35 columnas de datos enriquecidos para análisis estadístico
  - Todos los datos siguen siendo completamente anónimos (sin IDs de posts, títulos o contenido)

### 1.5.0
- **Mejoras en el sistema de actualizaciones**: 
  - Corrección del error 401 para repositorios públicos (no se usa token inválido)
  - Validación mejorada de tokens de GitHub
  - Mejora en el manejo del directorio `-main` al actualizar desde GitHub
  - Botones para limpiar errores del updater y historial de verificaciones en la página de depuración
- **Mejoras en la interfaz de depuración**:
  - Botón para limpiar errores del updater
  - Botón para limpiar historial de verificaciones
  - Mejor organización de los logs del sistema de actualizaciones

### 1.4.0
- 🔄 **Sistema de Actualizaciones Automáticas desde GitHub**
  - Verificación automática de nuevas versiones cada hora
  - Actualización directa desde el panel de administración de WordPress
  - Soporte para repositorios públicos y privados (con token)
  - Página de diagnóstico con estado del updater
  - Logs de verificaciones y errores del updater
- 🧹 **Limpieza de Referencias de Contenido (ContentReference)**
  - Detecta y elimina referencias LLM como `ContentReference [oaicite:=0](index=0)`
  - Soporte para múltiples variaciones del formato
- 🔗 **Limpieza de Parámetros UTM de Enlaces**
  - Elimina parámetros UTM de enlaces como `?utm_source=chatgpt.com`
  - Soporte para todos los parámetros utm_* (utm_source, utm_medium, utm_campaign, etc.)
  - Procesamiento robusto de URLs usando parse_url() y parse_str()
- **Análisis previo mejorado**: Ahora procesa TODOS los posts (sin límite de 100)
- **Interfaz actualizada**: Nuevas opciones de selección de tipos de limpieza

### 1.3.0
- **Registro de actividad mejorado**: Ahora se muestra exactamente qué cambios se realizaron y dónde (párrafo, bloque CSS, etc.)
- **Opciones de configuración de limpieza**: Añadidas opciones para activar/desactivar limpieza de parámetros y Unicode (por defecto desactivadas)
- **Análisis previo**: Sistema de análisis previo que muestra qué elementos se encontraron antes de limpiar
- **Selección de tipos de limpieza**: Interfaz para seleccionar qué tipos de limpieza aplicar con botón "Seleccionar todo"
- **Ubicaciones detalladas en logs**: Los logs ahora incluyen información sobre dónde se realizaron los cambios (Gutenberg Block, Paragraph, Heading, etc.)
- **Control granular**: Los usuarios pueden elegir exactamente qué limpiar basándose en el análisis previo

### 1.2.1
- **Detección mejorada de bloques de Gutenberg**: Sistema mejorado para detectar bloques de Gutenberg por clases CSS cuando no hay comentarios de Gutenberg disponibles
- **Preservación de bloques RankMath FAQ**: El sistema ahora detecta y preserva bloques de RankMath FAQ por sus clases CSS específicas (`wp-block-rank-math-faq-block` y `rank-math-block`)
- **Extracción robusta de bloques div**: Implementado método robusto para extraer bloques div completos contando correctamente las etiquetas de apertura y cierre
- **Compatibilidad mejorada**: El plugin ahora funciona correctamente con bloques de Gutenberg que no tienen comentarios HTML en el contenido guardado

### 1.2.0
- **Preservación mejorada de bloques de Gutenberg**: Sistema mejorado para preservar bloques completos de Gutenberg (comentarios + contenido) sin procesarlos
- **Placeholders de texto**: Uso de placeholders de texto en lugar de comentarios HTML para evitar que DOMDocument los elimine
- **Captura de bloques completos**: El sistema ahora captura bloques completos desde el comentario de apertura hasta el de cierre, preservando todo el contenido
- **Verificación de coincidencia**: Verificación automática de que los comentarios de apertura y cierre correspondan al mismo bloque antes de preservarlo
- **Restauración mejorada**: Sistema mejorado de restauración que maneja placeholders escapados como entidades HTML

### 1.1.9
- **Preservación de bloques de Gutenberg**: Solucionado el problema donde los bloques de Gutenberg (especialmente RankMath FAQ) se eliminaban o corrompían durante la limpieza
- **Extracción de comentarios de bloques**: El sistema ahora extrae y preserva los comentarios HTML de bloques de Gutenberg (`<!-- wp:namespace/block-name -->`) antes de procesar el HTML
- **Restauración automática**: Los comentarios de bloques se restauran automáticamente después de la limpieza, manteniendo la estructura completa del bloque
- **Compatibilidad con Gutenberg**: El plugin ahora es completamente compatible con todos los bloques de Gutenberg, incluyendo bloques personalizados de plugins como RankMath

### 1.1.8
- **Mejora en decodificación Unicode**: Sistema mejorado para manejar múltiples formatos de secuencias Unicode (`u003c`, `\u003c`, `&#x003c;`, etc.)
- **Verificación de caracteres invisibles**: El sistema ahora verifica que los caracteres Unicode decodificados no sean caracteres invisibles que estamos eliminando
- **Eliminación inteligente**: Los caracteres invisibles se eliminan automáticamente durante la decodificación, evitando problemas de formateo
- **Soporte para múltiples formatos**: Ahora se manejan correctamente formatos como `u003c`, `\u003c`, y entidades HTML hexadecimales

### 1.1.7
- **Corrección de formato HTML**: Solucionado el problema donde el texto aparecía con secuencias Unicode mal formateadas (ej: `u003c` en lugar de `<`) después de eliminar caracteres Unicode invisibles
- **Decodificación de secuencias Unicode**: Implementada decodificación automática de secuencias Unicode como `u003c`, `u003e`, etc. a sus caracteres HTML correspondientes
- **Mejora en el formateo**: El HTML ahora se mantiene correctamente formateado después de la limpieza, asegurando que las etiquetas HTML se muestren correctamente

### 1.1.6
- **Corrección crítica de procesamiento por lotes**: Solucionado el problema donde el proceso se quedaba atascado en un offset específico (ej: offset 64) y no continuaba procesando posts
- **Mejora en la consulta de posts**: Ahora se obtienen todos los IDs al inicio del proceso y se procesan usando `post__in` en lugar de `offset`, evitando problemas con filtros de plugins
- **Mayor confiabilidad**: El sistema ahora procesa exactamente los posts identificados al inicio, sin depender de consultas con offset que pueden fallar
- **Actualización directa sin hooks**: Implementada actualización directa a la base de datos para evitar ejecutar los hooks de `save_post` (WPML, WooCommerce, RankMath, Divi Builder, etc.) que causaban bloqueos
- **Rendimiento mejorado**: El proceso de limpieza es ahora mucho más rápido al evitar la ejecución de todos los callbacks de plugins durante la actualización de posts

### 1.1.5
- **Detección de conflictos de plugins**: Sistema mejorado para identificar qué plugins pueden estar causando que el proceso de limpieza se detenga o sea lento
- **Medición de tiempos de procesamiento**: Registro detallado del tiempo que tarda cada post en procesarse y actualizarse
- **Información de plugins activos**: Nueva sección en la pestaña de Depuración que muestra todos los plugins activos y sus versiones
- **Análisis de hooks de WordPress**: Visualización de todos los hooks relacionados con `save_post` que podrían interferir con el proceso
- **Alertas de posts lentos**: El sistema detecta y registra posts que tardan más de 2 segundos en actualizarse o más de 5 segundos en procesarse completamente
- **Logging mejorado**: Información del sistema (plugins y hooks) se registra al inicio de cada proceso de limpieza para facilitar el diagnóstico

### 1.1.4
- **Información del sistema mejorada**: Valores recomendados mostrados junto a los valores actuales con indicadores de color (verde para valores correctos, rojo para valores inferiores)
- **Descarga de log de depuración**: Nuevo botón para descargar todos los logs de depuración y errores en un archivo
- **Comparación automática de valores**: El sistema compara automáticamente los valores del servidor con los recomendados y los marca visualmente
- **Sistema de actualización automática**: Verificación y actualización automática de opciones cuando se actualiza el plugin
- **Corrección de problemas de actualización**: Solucionado el problema de pantalla en blanco durante las actualizaciones del plugin

### 1.1.3
- **Sistema de logging mejorado**: Logging detallado de memoria, tiempo de ejecución y progreso en cada lote
- **Diagnóstico de errores mejorado**: Captura y registro de errores AJAX desde el cliente con información detallada
- **Información de depuración**: Cada lote registra uso de memoria, tiempo restante y progreso porcentual
- **Mejor manejo de timeouts**: Detección y reintento automático con información detallada del error
- **Logging de estado del proceso**: Registro del estado completo antes y después de cada lote

### 1.1.2
- **Menú principal en la barra de administración**: El plugin ahora aparece como un menú principal en lugar de estar en "Herramientas"
- **Configuración de posts por lote**: Nueva opción para ajustar el número de posts procesados por lote (recomendado entre 10 y 30 según el servidor)
- **Pestaña de Depuración**: Nueva sección para diagnosticar errores y problemas durante el proceso de limpieza
- **Sistema de logging de errores**: Captura automática de errores durante el procesamiento para facilitar el diagnóstico
- **Información del sistema**: Muestra detalles del entorno (PHP, WordPress, memoria, etc.) en la pestaña de depuración
- **Telemetría anónima (opt-in, activada por defecto)**: Sistema opcional para compartir estadísticas anónimas con propósitos de investigación y estudios sobre LLMs y buscadores
- Limpieza de caracteres Unicode invisibles (Zero Width, control bidi, BOM, Soft Hyphen, Variation Selectors, Tag Characters, etc.)
- Estadísticas por tipo de Unicode en el log (prefijo "unicode: ...") incluso cuando no hay atributos HTML
- API de filtro `llm_trace_cleaner_unicode_map` para personalizar qué caracteres eliminar

### 1.1.1
- Ampliación de la lista de atributos eliminados (soporte para `data-offset-key`, `data-message-id`, `data-sender`/`data-role`, `data-token-index`, `data-model`, `data-render-timestamp`, `data-update-timestamp`, `data-confidence`, `data-temperature`, `data-seed`, `data-step`, `data-lang`, `data-format`, `data-annotation`, `data-reference`, `data-version`, `data-error`, `data-stream-id`, `data-chunk`, `data-context-id`, `data-user-id`, `data-ui-state`)
- Nueva API de filtro `llm_trace_cleaner_attributes` para extender atributos sin tocar el core
- El logger usa la misma lista del limpiador para detectar y reportar atributos eliminados con precisión
- Botones “Seleccionar todos / Deseleccionar” en la lista de Bots/LLMs a detectar
- Recarga automática de la página al finalizar el procesamiento para mostrar los nuevos logs

### 1.1.0
- Sistema de gestión de caché inteligente
- Desactivación automática de caché durante la limpieza
- Limpieza de caché después de modificar posts
- Detección de bots/LLMs para desactivar caché
- Detección inteligente de atributos eliminados en el log
- Paginación en el registro de actividad
- Archivo de log descargable
- Compatibilidad mejorada con plugins de caché (LiteSpeed, WP Rocket, W3 Total Cache, etc.)

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

## 📡 Telemetría y Privacidad

Este plugin incluye una **opción opcional** para compartir estadísticas anónimas con propósitos de investigación y estudios sobre LLMs y buscadores.

### ¿Qué datos se recopilan?

**Solo datos agregados y completamente anónimos:**
- Número total de páginas procesadas
- Número de páginas con datos ocultos encontrados
- Tipos específicos de atributos y caracteres Unicode encontrados (ej: `data-start`, `data-llm`, `unicode: Zero Width Space`)
- Contadores por tipo de rastro encontrado
- Versión del plugin, WordPress y PHP (para análisis de compatibilidad)

### ¿Qué NO se recopila?

- ❌ URLs de tu sitio web
- ❌ Títulos de posts o páginas
- ❌ IDs de posts
- ❌ Contenido del sitio
- ❌ Información personal o sensible
- ❌ Datos que puedan identificar tu sitio o usuarios

### Propósito de la recopilación

Los datos anónimos se utilizan exclusivamente para:
- **Investigación académica**: Estudiar cómo los LLMs marcan el contenido
- **Análisis de tendencias**: Entender qué tipos de rastros son más comunes
- **Mejora del plugin**: Priorizar qué atributos y caracteres eliminar
- **Estudios sobre buscadores**: Analizar cómo los buscadores interactúan con contenido generado por LLMs

### Control del usuario

- ✅ **Opt-in explícito**: Debes activar manualmente la opción "Compartir estadísticas anónimas"
- ✅ **Puedes desactivarlo en cualquier momento**: Simplemente desmarca la opción en la configuración
- ✅ **No afecta la funcionalidad**: El plugin funciona perfectamente sin telemetría

### Transparencia

Todos los datos se envían de forma segura (HTTPS) y se almacenan de manera agregada. No se puede identificar ningún sitio individual a partir de los datos recopilados.

## 🔄 Actualizaciones Automáticas

El plugin incluye un sistema de actualizaciones automáticas desde GitHub:

### ¿Cómo funciona?

1. **Verificación automática**: Cada hora, el plugin consulta la última versión en GitHub
2. **Notificación**: Si hay una nueva versión, aparece en `Plugins > Actualizaciones`
3. **Actualización con un clic**: Puedes actualizar directamente desde el panel de WordPress
4. **Diagnóstico**: En `LLM Trace Cleaner > Depuración` puedes ver el estado del sistema

### Repositorios públicos

Para repositorios públicos (como este), no se necesita configuración adicional. Las actualizaciones funcionan automáticamente.

### Repositorios privados

Si usas un fork privado:

1. Ve a https://github.com/settings/tokens
2. Genera un nuevo token con permiso `repo`
3. Crea un archivo `.env` en la raíz del plugin:
   ```
   LLM_TRACE_CLEANER_GITHUB_TOKEN=ghp_tu_token_aqui
   ```

### Forzar verificación

Para forzar una verificación de actualizaciones:

1. Ve a `LLM Trace Cleaner > Depuración`
2. En la sección "Sistema de Actualizaciones desde GitHub"
3. Haz clic en "Forzar Verificación de Actualizaciones"

## 📞 Soporte

Si encuentras algún problema o tienes sugerencias:

1. Abre un [issue](https://github.com/yaggoSEO/llm-trace-cleaner/issues)
2. Describe el problema detalladamente
3. Incluye información sobre tu entorno (versión de WordPress, PHP, etc.)
4. Si el problema persiste, revisa la pestaña **Depuración** en el menú del plugin para ver logs de errores

---

⭐ Si este plugin te resulta útil, considera darle una estrella en GitHub.

## 📌 Notas

- El plugin está optimizado para procesar grandes cantidades de contenido sin sobrecargar el servidor
- El sistema de logging ayuda a rastrear todos los cambios realizados
- La limpieza automática solo se ejecuta en posts y páginas publicados
- El plugin es compatible con la mayoría de temas y plugins de WordPress


