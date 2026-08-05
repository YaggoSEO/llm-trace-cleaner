# LLM Trace Cleaner

Plugin de WordPress que elimina rastros técnicos de herramientas LLM en el HTML y, desde la v1.7, también audita y sanea metadatos de imágenes (JPEG/PNG/WebP).

## Descripción

**LLM Trace Cleaner** limpia atributos de rastreo que ChatGPT, Claude, Gemini y similares insertan al pegar contenido, y añade un módulo opcional de **privacidad y gobierno de metadatos de imágenes**.

### Módulo de imágenes (v1.7+)

Menú: **LLM Trace Cleaner → Imágenes**.

Qué hace:
- Auditar EXIF/IPTC/XMP y propiedades disponibles (Imagick preferente; GD como fallback de limpieza).
- Eliminar datos sensibles (GPS, serie, prompts/workflows heurísticos, comentarios).
- Aplicar perfiles: Privacidad máxima, Corporativo, SEO local, Fotografía original, Imagen generada/editada con IA.
- **Editar campos del perfil** (empresa, ciudad, copyright, etc.) en la pestaña Perfiles. SEO local usa *ubicación mostrada*, no GPS de captura.
- Dry run (simulación), backup opcional y restauración.
- Procesamiento manual, por lotes y (opcional) en nuevas subidas.
- Distinguir **ubicación creada** vs **ubicación mostrada** / ámbito geográfico.
- Detección heurística de C2PA: por defecto **no modifica** si hay indicios.
- **ExifTool opcional** (v1.8+): escritura completa EXIF/IPTC/XMP; desactivado por defecto.
- Reglas condicionales por MIME/carpeta/autor; AVIF opcional; export CSV/JSON de informes.

#### ExifTool (opcional)

1. Instala ExifTool en el servidor (p. ej. `apt install libimage-exiftool-perl` o binario desde exiftool.org).
2. En **Imágenes → Ajustes**, activa ExifTool. La ruta por defecto es `/usr/bin/exiftool` (también se prueban `/usr/local/bin/exiftool` y otras rutas estándar).
3. Pulsa **Probar ExifTool**.
4. Guarda ajustes y aplica un perfil (p. ej. SEO local con ciudad rellenada).

Sin ExifTool el plugin no falla: limpia con GD/Imagick y avisa si no puede escribir campos ricos.

Qué **no** hace:
- No elimina marcas de agua incrustadas en píxeles.
- No garantiza indetectabilidad frente a detectores de IA ni borra SynthID.
- No inventa cámara, objetivo, serie, fecha o GPS.
- No valida manifiestos C2PA (solo heurística + aviso).

Valores iniciales seguros: módulo desactivado, dry run activado, backup activado, detener ante C2PA, ExifTool desactivado.

Medios remotos (S3/offload sin archivo local): `unsupported_remote_storage`.

Pruebas unitarias del módulo:
```bash
php tests/unit/run-image-unit-tests.php
```

---

## Características (HTML)

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

- `data-start` - **Uso**: Marca la posición inicial de un fragmento de texto en el contenido original. **Riesgo**: Expone la estructura interna del contenido generado, permitiendo identificar qué partes fueron generadas por el LLM y en qué orden.

- `data-end` - **Uso**: Marca la posición final de un fragmento de texto. **Riesgo**: Junto con `data-start`, permite reconstruir la estructura completa del contenido generado, revelando información sobre el proceso de generación.

- `data-is-last-node` - **Uso**: Indica si un nodo es el último en una secuencia. **Riesgo**: Expone la estructura de árbol del contenido, información técnica innecesaria para el usuario final.

- `data-is-only-node` - **Uso**: Indica si un nodo es el único en su contenedor. **Riesgo**: Información estructural que puede ser utilizada para identificar patrones de generación del LLM.

- `data-llm` - **Uso**: Marca genéricamente contenido generado por un LLM. **Riesgo**: Identifica directamente que el contenido fue generado por IA, lo que puede afectar la percepción de originalidad y SEO.

- `data-pm-slice` - **Uso**: Identifica "slices" o fragmentos de contenido en editores ProseMirror. **Riesgo**: Expone la estructura interna del editor, información técnica que no debería estar en el HTML público.

- `data-llm-id` - **Uso**: Identificador único asignado por el LLM a cada elemento. **Riesgo**: Permite rastrear y correlacionar contenido generado por el mismo LLM, potencialmente identificando la fuente del contenido.

- `data-llm-trace` - **Uso**: Rastro completo del proceso de generación del LLM. **Riesgo**: Contiene información detallada sobre cómo se generó el contenido, incluyendo posibles metadatos sensibles.

- `data-original-text` - **Uso**: Almacena el texto original antes de cualquier modificación. **Riesgo**: Puede exponer información que el usuario pensó que había eliminado o modificado, comprometiendo la privacidad.

- `data-source-text` - **Uso**: Referencia al texto fuente utilizado para generar el contenido. **Riesgo**: Puede revelar fuentes de información o contenido que el usuario no quiere que sea visible públicamente.

- `data-highlight` - **Uso**: Marca texto destacado o resaltado en la interfaz del LLM. **Riesgo**: Expone información sobre qué partes del contenido el LLM consideró importantes, información de interfaz que no debería estar en el HTML público.

- `data-entity` - **Uso**: Identifica entidades nombradas (personas, lugares, organizaciones) detectadas por el LLM. **Riesgo**: Puede exponer información sobre cómo el LLM interpretó el contenido, incluyendo posibles datos estructurados sensibles.

- `data-mention` - **Uso**: Marca menciones o referencias a otros elementos. **Riesgo**: Puede revelar relaciones internas o referencias cruzadas que el usuario no quiere exponer.

- `data-offset-key` - **Uso**: Clave de desplazamiento para identificar la posición exacta en el editor. **Riesgo**: Información técnica del editor que puede ser utilizada para identificar la herramienta utilizada y su versión.

- `data-message-id` - **Uso**: Identificador único de un mensaje en la conversación con el LLM. **Riesgo**: Permite correlacionar contenido con conversaciones específicas, potencialmente identificando sesiones de usuario.

- `data-sender` / `data-role` - **Uso**: Indica quién envió el mensaje (usuario o asistente). **Riesgo**: Expone la estructura de la conversación, revelando qué partes fueron generadas por el LLM vs. escritas por el usuario.

- `data-token-index` - **Uso**: Índice del token en la secuencia generada. **Riesgo**: Información técnica sobre el proceso de tokenización que puede ser utilizada para análisis forense del contenido.

- `data-model` - **Uso**: Identifica el modelo de LLM utilizado (ej: GPT-4, Claude-3). **Riesgo**: Expone directamente qué herramienta de IA se utilizó, información que puede afectar la percepción de originalidad.

- `data-render-timestamp` - **Uso**: Marca de tiempo de cuándo se renderizó el contenido. **Riesgo**: Puede exponer información sobre cuándo se generó el contenido, potencialmente revelando patrones de uso.

- `data-update-timestamp` - **Uso**: Marca de tiempo de la última actualización. **Riesgo**: Similar a `data-render-timestamp`, puede revelar información temporal sensible sobre el proceso de creación.

- `data-confidence` - **Uso**: Nivel de confianza del LLM en la respuesta generada. **Riesgo**: Expone información sobre la incertidumbre del modelo, lo que puede afectar la credibilidad del contenido.

- `data-temperature` - **Uso**: Parámetro de temperatura usado en la generación (controla la creatividad/aleatoriedad). **Riesgo**: Información técnica sobre los parámetros de generación que no debería ser pública.

- `data-seed` - **Uso**: Semilla utilizada para la generación aleatoria. **Riesgo**: Con la semilla y otros parámetros, teóricamente se podría reproducir la generación, comprometiendo la unicidad del contenido.

- `data-step` - **Uso**: Número de paso en el proceso de generación. **Riesgo**: Expone información sobre el proceso iterativo de generación, revelando detalles técnicos innecesarios.

- `data-lang` - **Uso**: Idioma detectado o especificado para el contenido. **Riesgo**: Aunque menos sensible, puede exponer información sobre el procesamiento del LLM que no es necesaria en el HTML público.

- `data-format` - **Uso**: Formato del contenido (markdown, HTML, texto plano). **Riesgo**: Información técnica sobre el formato que puede ser utilizada para identificar la herramienta de origen.

- `data-annotation` - **Uso**: Anotaciones o comentarios del LLM sobre el contenido. **Riesgo**: Puede contener información adicional o metadatos que el usuario no quiere exponer públicamente.

- `data-reference` - **Uso**: Referencias a fuentes o documentos utilizados. **Riesgo**: Puede exponer fuentes de información o referencias internas que el usuario prefiere mantener privadas.

- `data-version` - **Uso**: Versión del modelo o sistema utilizado. **Riesgo**: Expone información sobre la versión del LLM, útil para análisis forense del contenido.

- `data-error` - **Uso**: Información sobre errores durante la generación. **Riesgo**: Puede exponer información de depuración o errores técnicos que no deberían estar en el HTML público.

- `data-stream-id` - **Uso**: Identificador del stream de generación. **Riesgo**: Permite correlacionar contenido generado en el mismo stream, potencialmente identificando sesiones o conversaciones.

- `data-chunk` - **Uso**: Identifica fragmentos o "chunks" del contenido generado. **Riesgo**: Expone cómo el LLM dividió el contenido en partes, información estructural innecesaria.

- `data-context-id` - **Uso**: Identificador del contexto de la conversación. **Riesgo**: Permite correlacionar contenido con contextos específicos, potencialmente identificando conversaciones o sesiones.

- `data-user-id` - **Uso**: Identificador del usuario que generó el contenido. **Riesgo**: **ALTO RIESGO**: Puede exponer información de identificación del usuario, comprometiendo seriamente la privacidad.

- `data-ui-state` - **Uso**: Estado de la interfaz de usuario cuando se generó el contenido. **Riesgo**: Expone información sobre el estado de la UI del LLM, información técnica que no debería estar en el HTML público.

- Cualquier atributo `id` cuyo valor empiece por `model-response-message-contentr_` - **Uso**: Identificadores automáticos generados por algunos LLMs para elementos de respuesta. **Riesgo**: Permite identificar directamente contenido generado por LLMs específicos, afectando la percepción de originalidad y potencialmente el SEO.

### Referencias de contenido LLM eliminadas

El plugin también elimina referencias de contenido que algunos LLMs agregan al texto:

- `ContentReference [oaicite:=0](index=0)` y variaciones - **Uso**: Referencias a fuentes o citas utilizadas por el LLM (especialmente en modelos como ChatGPT con búsqueda web). **Riesgo**: Expone que el contenido fue generado por un LLM y puede revelar qué fuentes fueron consultadas, afectando la percepción de originalidad y potencialmente exponiendo información sobre el proceso de investigación del modelo.

- `[oaicite:0]`, `[oaicite:=1]`, etc. - **Uso**: Marcadores de citas abreviados insertados automáticamente por algunos LLMs. **Riesgo**: Similar a las referencias completas, estos marcadores identifican claramente el contenido como generado por IA y pueden afectar negativamente el SEO y la credibilidad del contenido.

### Parámetros UTM eliminados de enlaces

El plugin elimina parámetros UTM de los enlaces que algunos LLMs agregan automáticamente:

- `?utm_source=chatgpt.com` - **Uso**: Identifica que el enlace proviene de ChatGPT. **Riesgo**: Expone directamente que el contenido fue copiado desde ChatGPT, afectando la percepción de originalidad y potencialmente el SEO. Los buscadores pueden penalizar contenido que claramente proviene de herramientas de IA.

- `?utm_medium=chat` - **Uso**: Indica que el medio de origen fue una conversación/chat. **Riesgo**: Similar a `utm_source`, identifica el método de obtención del contenido, revelando que fue generado o copiado desde una herramienta de chat.

- `?utm_campaign=...` - **Uso**: Identifica la campaña o contexto específico dentro del LLM. **Riesgo**: Puede exponer información adicional sobre el contexto en el que se generó el contenido, incluyendo posibles identificadores de sesión o campaña.

- Y cualquier otro parámetro `utm_*` - **Uso**: Parámetros de seguimiento estándar de marketing. **Riesgo**: Todos los parámetros UTM pueden ser utilizados para rastrear el origen del tráfico y correlacionar contenido con sesiones específicas del LLM, comprometiendo la privacidad y la originalidad percibida del contenido.

### Caracteres Unicode invisibles eliminados

El plugin también elimina caracteres invisibles que suelen usarse para marcas, manipulación del renderizado o confusión visual. Algunos ejemplos:

- **Zero Width Space (U+200B), ZWNJ (U+200C), ZWJ (U+200D)** - **Uso**: Caracteres de ancho cero utilizados para controlar el comportamiento de palabras y espacios en diferentes idiomas. **Riesgo**: Pueden ser utilizados como marcas de agua invisibles para rastrear contenido. Los buscadores y sistemas de detección de plagio pueden identificar estos caracteres como señales de contenido generado o copiado. También pueden causar problemas de indexación y búsqueda.

- **Zero Width No-Break Space / BOM (U+FEFF)** - **Uso**: Marca de orden de bytes (BOM) o espacio de no separación invisible. **Riesgo**: Puede ser utilizado como marca de agua para identificar la fuente del contenido. Su presencia puede causar problemas de codificación y renderizado en diferentes navegadores y sistemas.

- **Word Joiner (U+2060), Invisible Separator (U+2063), Invisible Plus (U+2064), Invisible Times (U+2062)** - **Uso**: Caracteres invisibles para controlar el comportamiento de palabras y operadores matemáticos. **Riesgo**: Pueden ser utilizados como marcas de agua o para ocultar información. Su presencia puede afectar la indexación del contenido y ser detectada por sistemas de análisis de texto.

- **Soft Hyphen (U+00AD)** - **Uso**: Guion suave que solo se muestra cuando es necesario para dividir palabras. **Riesgo**: Aunque tiene un uso legítimo, puede ser utilizado para marcar contenido o causar problemas de renderizado. Los buscadores pueden interpretarlo de manera inconsistente.

- **Marcas de direccionalidad y control bidi: LRM (U+200E), RLM (U+200F), LRE/RLE/PDF/LRO/RLO (U+202A–U+202E), aislantes (U+2066–U+2069)** - **Uso**: Controlan la dirección del texto (izquierda a derecha, derecha a izquierda) en idiomas bidireccionales. **Riesgo**: Pueden ser utilizados para ocultar información o manipular el renderizado del texto. Su uso incorrecto puede causar problemas graves de visualización y ser detectado como contenido sospechoso por sistemas de seguridad.

- **Mongolian Vowel Separator (U+180E)** - **Uso**: Separador de vocales en el idioma mongol. **Riesgo**: Raramente necesario fuera de contextos específicos de idioma mongol. Su presencia puede ser una señal de contenido manipulado o marcado.

- **Tag Characters (U+E0000–U+E007F)** - **Uso**: Caracteres de etiquetado privado utilizados para metadatos. **Riesgo**: **ALTO RIESGO**: Estos caracteres están específicamente diseñados para almacenar información oculta y pueden contener marcas de agua, identificadores de fuente, o metadatos sensibles. Su presencia es una señal clara de contenido marcado o rastreado.

- **Invisible Ideographic Space (U+3000)** - **Uso**: Espacio ideográfico invisible usado en idiomas CJK (chino, japonés, coreano). **Riesgo**: Puede ser utilizado como marca de agua o causar problemas de renderizado en contextos no CJK. Su presencia puede afectar la indexación y búsqueda del contenido.

- **Object Replacement Character (U+FFFC)** - **Uso**: Marcador de posición para objetos embebidos. **Riesgo**: Puede causar problemas de renderizado y ser utilizado para ocultar información. Su presencia puede indicar contenido mal formateado o manipulado.

- **Variation Selectors (U+FE00–U+FE0F)** - **Uso**: Controlan variaciones visuales de caracteres Unicode. **Riesgo**: Pueden ser utilizados para crear marcas de agua invisibles o manipular la apariencia del texto. Su uso excesivo puede ser detectado como contenido sospechoso.

**Riesgo general de caracteres Unicode invisibles**: Estos caracteres pueden ser utilizados para crear "marcas de agua" invisibles que permiten a los LLMs rastrear y verificar si el contenido fue generado por ellos. Además, pueden causar problemas de indexación en buscadores, afectar la accesibilidad, y ser detectados por sistemas de detección de plagio o contenido generado por IA.

Estos caracteres se registran en el log con el prefijo "unicode: ..." para que puedas ver exactamente cuál fue eliminado.

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

```text
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

### 1.6.4
- **Corrección crítica**: Limpieza de parámetros UTM y caracteres Unicode en bloques de Gutenberg y page builders antes de restaurarlos. Los bloques se extraían antes de la limpieza y se restauraban después, reintroduciendo los elementos que debían eliminarse. Ahora se limpian también dentro de los bloques antes de restaurarlos.
- **Soporte completo para page builders**: Detección y limpieza en más de 30 page builders y extensiones de Gutenberg, incluyendo Elementor, Divi, Bricks, WPBakery, Beaver Builder, Oxygen, Thrive Architect, Brizy, SiteOrigin, Kadence, GeneratePress, Astra, Spectra, Stackable, Zion, Live Composer, Themify, Cornerstone, Fusion Builder, KingComposer, Qubely, Gutentor, Neve, CoBlocks, SeedProd, GreenShift, Getwid, Atomic Blocks, Advanced Gutenberg, Pootle, MotoPress, BoldGrid, Page Builder Sandwich, WP Page Builder, Visual Composer Website Builder, y Gutenberg Block Collection.
- **Mejoras en logs de depuración**: Sistema de logging mejorado que captura información detallada sobre la limpieza de UTM y Unicode, incluyendo conteos antes/después, detección de elementos en bloques de page builders, y opciones de limpieza aplicadas. Esto facilita el diagnóstico cuando los usuarios reportan problemas.
- **Limpieza de código**: Eliminación de código de depuración temporal y logs innecesarios.

### 1.6.3
- Reorganización de la interfaz del Sistema de Actualizaciones: información del updater integrada en la tabla principal, eliminada fila "Token de GitHub" para repos públicos
- Correcciones: botón "Limpiar todos los logs" corregido, validación mejorada para evitar fechas/horas duplicadas o en blanco en logs

### 1.6.2
- Análisis previo mejorado: tabla seleccionable con posts/páginas y elementos encontrados, botones de selección masiva, tabla colapsable
- Detección mejorada: logging automático de content references y UTM parameters eliminados, captura de URLs completas con parámetros UTM
- Simplificación de logs del updater: solo último error y última verificación, visualización como información de estado

### 1.6.1
- Corrección crítica de persistencia de transients: persistencia directa en base de datos para evitar problemas con object cache (Redis, Memcached), optimización de almacenamiento de IDs

### 1.6.0
- Telemetría mejorada: captura de Content References y UTM Parameters, métricas de rendimiento (tiempo, posts/segundo, ratio), Google Sheets con 35 columnas de datos enriquecidos

### 1.5.0
- Sistema de actualizaciones: corrección de error 401 para repos públicos, validación mejorada de tokens, manejo del directorio `-main`
- Interfaz de depuración: botones para limpiar errores del updater e historial de verificaciones

### 1.4.0
- Sistema de Actualizaciones Automáticas desde GitHub: verificación automática cada hora, actualización desde panel de WordPress, soporte para repos públicos y privados, página de diagnóstico
- Limpieza de Referencias de Contenido (ContentReference): detección y eliminación de referencias LLM con múltiples variaciones
- Limpieza de Parámetros UTM: eliminación de parámetros utm_* de enlaces, procesamiento robusto de URLs
- Análisis previo mejorado: procesa todos los posts sin límite, interfaz actualizada con opciones de selección

### 1.3.0
- Registro de actividad mejorado: muestra cambios exactos y ubicaciones (párrafo, bloque CSS, etc.)
- Opciones de configuración: activar/desactivar limpieza de parámetros y Unicode (por defecto desactivadas)
- Análisis previo y selección granular: sistema de análisis previo, interfaz para seleccionar tipos de limpieza, control granular basado en análisis

### 1.2.1
- Detección mejorada de bloques de Gutenberg: detección por clases CSS cuando no hay comentarios disponibles
- Preservación de bloques RankMath FAQ: detección por clases CSS específicas
- Extracción robusta de bloques div: método mejorado para bloques completos

### 1.2.0
- Preservación mejorada de bloques de Gutenberg: sistema mejorado con placeholders de texto, captura de bloques completos, verificación de coincidencia, restauración mejorada

### 1.1.9
- Preservación de bloques de Gutenberg: extracción y preservación de comentarios HTML, restauración automática, compatibilidad completa con bloques personalizados

### 1.1.8
- Mejora en decodificación Unicode: soporte para múltiples formatos (u003c, \u003c, &#x003c;), verificación y eliminación inteligente de caracteres invisibles durante decodificación

### 1.1.7
- Corrección de formato HTML: decodificación automática de secuencias Unicode mal formateadas, mejora en formateo del HTML

### 1.1.6
- Corrección crítica de procesamiento por lotes: solución de bloqueos en offsets específicos, consulta mejorada usando `post__in`, actualización directa sin hooks para evitar bloqueos de plugins

### 1.1.5
- Detección de conflictos: identificación de plugins que causan problemas, medición de tiempos, información de plugins activos y hooks de WordPress, alertas de posts lentos

### 1.1.4
- Información del sistema mejorada: valores recomendados con indicadores de color, comparación automática, descarga de log de depuración
- Sistema de actualización automática: verificación y actualización de opciones, corrección de pantalla en blanco

### 1.1.3
- Sistema de logging mejorado: logging detallado de memoria y tiempo, diagnóstico de errores AJAX, mejor manejo de timeouts, registro de estado del proceso

### 1.1.2
- Menú principal en barra de administración, configuración de posts por lote, pestaña de Depuración
- Telemetría anónima (opt-in): sistema para compartir estadísticas anónimas
- Limpieza de caracteres Unicode invisibles: soporte completo con estadísticas por tipo, API de filtro `llm_trace_cleaner_unicode_map`

### 1.1.1
- Ampliación de atributos eliminados: soporte para 23 nuevos atributos de rastreo
- API de filtro `llm_trace_cleaner_attributes` para extender atributos
- Mejoras de interfaz: botones de selección masiva, recarga automática al finalizar

### 1.1.0
- Sistema de gestión de caché inteligente: desactivación automática durante limpieza, limpieza después de modificar posts, detección de bots/LLMs
- Mejoras de logging: detección inteligente de atributos, paginación, archivo descargable
- Compatibilidad mejorada con plugins de caché (LiteSpeed, WP Rocket, W3 Total Cache, etc.)

### 1.0.0
- Versión inicial: limpieza automática al guardar, limpieza manual con procesamiento por lotes, sistema de logging completo, interfaz de administración, barra de progreso

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz un fork del proyecto
2. Crea una rama para tu característica (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

```text
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

- Website: <https://yaggoseo.com>
- GitHub: <https://github.com/yaggoSEO>

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

1. Ve a <https://github.com/settings/tokens>
2. Genera un nuevo token con permiso `repo`
3. Crea un archivo `.env` en la raíz del plugin:
   ```env
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
