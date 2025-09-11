#!/bin/bash

# Script para publicar una release del plugin Regiones y Comunas de Chile
# Autor: Estudio MOCA

# Colores para mensajes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Función para mostrar mensajes de error y salir
error_exit() {
  echo -e "${RED}Error: $1${NC}" >&2
  exit 1
}

# Función para mostrar mensajes informativos
info() {
  echo -e "${YELLOW}$1${NC}"
}

# Función para mostrar mensajes de éxito
success() {
  echo -e "${GREEN}$1${NC}"
}

# Verificar si gh (GitHub CLI) está instalado
if ! command -v gh &> /dev/null; then
  error_exit "GitHub CLI (gh) no está instalado. Por favor, instálalo primero: https://cli.github.com/"
fi

# Verificar si estamos en un repositorio git
if [ ! -d .git ]; then
  error_exit "No estás en un repositorio git. Ejecuta este script desde la raíz del repositorio."
fi

# Obtener la versión actual del plugin
VERSION=$(grep "Version:" em-regiones-comunas.php | awk -F': ' '{print $2}' | tr -d ' \r\n')
if [ -z "$VERSION" ]; then
  error_exit "No se pudo obtener la versión del plugin desde em-regiones-comunas.php"
fi

# Verificar si existe el archivo CHANGELOG.md
if [ ! -f "CHANGELOG.md" ]; then
  error_exit "No se encontró el archivo CHANGELOG.md"
fi

# Crear el paquete zip
info "Creando paquete del plugin versión $VERSION..."
make build || error_exit "Error al crear el paquete del plugin"

# Verificar si existe el tag
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
  info "El tag v$VERSION ya existe. ¿Deseas eliminarlo y crearlo de nuevo? (s/n)"
  read -r response
  if [[ "$response" =~ ^([sS])$ ]]; then
    info "Eliminando tag existente..."
    git tag -d "v$VERSION" || error_exit "Error al eliminar el tag local"
    git push --delete origin "v$VERSION" 2>/dev/null || info "El tag remoto no existía o no se pudo eliminar"
  else
    info "Continuando con el tag existente..."
  fi
fi

# Crear tag si no existe
if ! git rev-parse "v$VERSION" >/dev/null 2>&1; then
  info "Creando tag v$VERSION..."
  git tag -a "v$VERSION" -m "Release versión $VERSION" || error_exit "Error al crear el tag"
fi

# Subir tag a GitHub
info "Subiendo tag a GitHub..."
git push origin --tags || error_exit "Error al subir el tag a GitHub"

# Crear release en GitHub
info "Creando release en GitHub..."
gh release create "v$VERSION" \
  --title "v$VERSION" \
  --notes-file CHANGELOG.md \
  --latest \
  "dist/em-regiones-comunas.zip" || error_exit "Error al crear la release en GitHub"

success "✅ Release v$VERSION publicada correctamente!"
success "📦 Archivo zip: dist/em-regiones-comunas.zip"
success "🔗 Visita la página de releases para ver la publicación"
