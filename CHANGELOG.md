# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.1] - 2026-11-08

### Corregido

- En WooCommerce › Ajustes › General, "País / Provincia" de la dirección de la tienda volvía a mostrar Afganistán al elegir una comuna de Chile. Se guardaba bien, pero WooCommerce no marcaba la opción porque compara el id numérico de la comuna con el texto guardado.

## [2.3.0] - 2026-11-07

### Añadido

- Al crear o editar un pedido en wp-admin, las direcciones de facturación y envío muestran País, Región y Comuna seguidos y a todo el ancho. La Región es un selector y la Comuna lista solo las comunas de la región elegida, también al cargar la dirección del cliente o copiarla de facturación a envío.
- Los campos de País, Región y Comuna se rotulan así en el perfil de usuario y en el pedido.

## [2.2.0] - 2026-11-07

### Añadido

- En el perfil de usuario de wp-admin, las direcciones de facturación y envío muestran País, Región y Comuna seguidos. La Región es un selector y la Comuna lista solo las comunas de la región elegida; si un usuario tiene comuna sin región, la región se completa sola.

## [2.1.1] - 2026-11-07

### Corregido

- Los scripts y estilos se cargan solo en el carro, el checkout y Mi cuenta. Antes iban en todas las páginas y, con el checkout de bloques, arrastraban los nonces de `wp-api-fetch` y `wc-blocks-middleware` al HTML, lo que rompía la caché de página completa.

## [2.1.0] - 2026-10-31

### Añadido

- Las comunas se listan en orden alfabético; en la Región Metropolitana, Santiago, Las Condes y Providencia van primero.
- La Región Metropolitana aparece primera en el selector de regiones.
- El campo de país muestra "País" en lugar de "País/Región".

### Mejorado

- Los scripts y estilos se versionan por fecha de modificación, así el navegador no sirve copias antiguas tras actualizar el plugin.

### Corregido

- Las comunas no aparecían en el selector: las consultas pasaban los nombres de tabla a `$wpdb->prepare()` con `%s` en vez de `%i`, lo que generaba SQL inválido.
- Variable `$options` sin inicializar en `custom_woocommerce_states()`.
- La etiqueta "Región" del checkout de bloques colapsaba, se superponía al valor y se encogía al elegir una región.

## [2.0.3] - 2025-10-03

### Corregido

- Plugin URI ahora apunta al repositorio de GitHub en lugar de duplicar el Author URI.

## [2.0.2] - 2025-10-03

### Corregido

- Corregido el nombre del plugin en la cabecera del plugin.

## [2.0.1] - 2025-10-03

### Añadido

- Sistema de caché para consultas de regiones y comunas usando WordPress Object Cache API.
- Función `clear_cache()` para limpiar el caché cuando sea necesario.
- Limpieza automática del caché al activar el plugin.

### Mejorado

- Rendimiento general del plugin mediante implementación de caché (reduce consultas a la base de datos).
- Sanitización de consultas SQL usando `esc_sql()` y `$wpdb->prepare()` con placeholders.
- Codificación de datos JSON usando `wp_json_encode()` para mayor seguridad.
- Nombre del plugin actualizado a "Regiones y Comunas de Chile para WooCommerce" para cumplir con lineamientos de WordPress.org.

### Corregido

- Cumplimiento con WordPress Coding Standards para consultas preparadas.
- Advertencias de seguridad en la salida de datos JSON.
- Formato del Text Domain cambiado de `em_regiones_comunas` a `em-regiones-comunas` (con guiones).

### Seguridad

- Todas las consultas SQL ahora usan preparación adecuada con placeholders.
- Escape apropiado de nombres de tablas en consultas dinámicas.
- Implementación de caché reduce superficie de ataque a la base de datos.

## [2.0.0] - 2025-10-03

### Cambiado

- Migración a versionado semántico (SemVer) desde formato de fecha.
- Versión cambiada de 25.09.04 a 2.0.0.

### Añadido (2.0.0)

- Licencia GPL v2 o posterior en la cabecera del plugin.
- Archivo LICENSE en el repositorio.
- Metadatos requeridos para WordPress.org en README.md.

### Corregido (2.0.0)

- Sanitización de consultas SQL para cumplir con estándares de WordPress.org.
- Mejora en la codificación de datos para prevenir vulnerabilidades XSS.

## [25.09.04] - 2025-09-04

### Añadido
- Compatibilidad con HPOS (High-Performance Order Storage) de WooCommerce.
- Declaración explícita de compatibilidad con "custom_order_tables" para el nuevo sistema de almacenamiento de órdenes de WooCommerce.

### Compatibilidad
- Requiere WooCommerce 9.0 o superior.
- Probado hasta WooCommerce 8.2.
- Requiere WordPress 6.3 o superior.
- Requiere PHP 8.1 o superior.

### Mejoras
- Optimización del código para mejor rendimiento con el nuevo sistema de almacenamiento de órdenes.
