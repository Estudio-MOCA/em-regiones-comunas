#!/bin/bash

# Script para empaquetar el plugin de Regiones y Comunas de Chile
# Autor: Estudio MOCA

# Obtener la versión del plugin desde el archivo principal
VERSION=$(grep "Version:" em-regiones-comunas.php | awk -F': ' '{print $2}' | tr -d ' \r\n')

# Crear directorio dist si no existe
mkdir -p dist

# Nombre del archivo zip
ZIP_FILE="dist/em-regiones-comunas.zip"

# Eliminar zip anterior si existe
if [ -f "$ZIP_FILE" ]; then
  rm "$ZIP_FILE"
  echo "Archivo zip anterior eliminado."
fi

# Crear zip con los archivos y carpetas especificados
zip -r "$ZIP_FILE" \
  assets \
  inc \
  languages \
  em-regiones-comunas.php \
  README.md \
  CHANGELOG.md

# Verificar si el zip se creó correctamente
if [ -f "$ZIP_FILE" ]; then
  echo "✅ Plugin empaquetado correctamente: $ZIP_FILE"
  # Mostrar el tamaño del archivo
  du -h "$ZIP_FILE"
else
  echo "❌ Error al empaquetar el plugin"
  exit 1
fi
