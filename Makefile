# Makefile para empaquetar el plugin de Regiones y Comunas de Chile
# Autor: Estudio MOCA

# Obtener la versión del plugin
VERSION := $(shell grep "Version:" em-regiones-comunas.php | awk -F': ' '{print $$2}' | tr -d ' \r\n')

# Nombre del archivo zip
ZIP_FILE := dist/em-regiones-comunas.zip

.PHONY: build clean

# Tarea principal para construir el zip
build:
	@echo "Empaquetando plugin versión $(VERSION)..."
	@mkdir -p dist
	@if [ -f "$(ZIP_FILE)" ]; then rm "$(ZIP_FILE)"; echo "Archivo zip anterior eliminado."; fi
	@zip -r "$(ZIP_FILE)" \
		assets \
		inc \
		languages \
		em-regiones-comunas.php \
		README.md \
		CHANGELOG.md
	@echo "✅ Plugin empaquetado correctamente: $(ZIP_FILE)"
	@du -h "$(ZIP_FILE)"

# Limpiar archivos generados
clean:
	@echo "Limpiando archivos generados..."
	@rm -rf dist
	@echo "✅ Limpieza completada"

# Mostrar ayuda
help:
	@echo "Uso del Makefile para empaquetar el plugin de Regiones y Comunas de Chile"
	@echo ""
	@echo "Comandos disponibles:"
	@echo "  make build  - Crear archivo zip del plugin en la carpeta dist"
	@echo "  make clean  - Eliminar la carpeta dist y todos los archivos generados"
	@echo "  make help   - Mostrar esta ayuda"
