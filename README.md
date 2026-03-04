# Generador de Constancias Docentes - CEPREUNA

Sistema web dinámico para la consulta y generación de constancias de trabajo para docentes del Centro Preuniversitario de la Universidad Nacional del Altiplano (Puno).

## 🚀 Características

- **Consulta por DNI**: Interfaz moderna para buscar registros específicos.
- **Generación de PDF al vuelo**: Utiliza TCPDF y FPDI para incrustar datos sobre un fondo de PDF oficial.

## 🛠️ Tecnologías

- **Backend**: PHP 7.4+
- **PDF Core**: [TCPDF](https://tcpdf.org/) & [FPDI](https://www.setasign.com/products/fpdi/about/)
- **Frontend**: HTML5, CSS3 (Vanilla), JavaScript (ES6+)
- **Dependencias**: Composer

## 📦 Instalación

1. Clona este repositorio:

   ```bash
   git clone https://github.com/tu-usuario/generador-constancias.git
   cd generador-constancias
   ```

2. Instala las dependencias de PHP vía Composer:

   ```bash
   composer install
   ```

3. Asegúrate de tener los archivos base en la raíz:
   - `fondo_2026.pdf` (La plantilla oficial).
   - `api.php`
   - `index.html`

## 💻 Ejecución Local

Para levantar el sistema rápidamente sin necesidad de un servidor externo como Apache o Nginx, ejecuta:

```bash
php -S localhost:8000 -t .
```

Luego, abre tu navegador en: [http://localhost:8000](http://localhost:8000)

## 📄 Notas de Implementación

- Los datos de los docentes están actualmente simulados en una función dentro de `api.php`. Para producción, se recomienda conectar esta función a una base de datos real o un servicio API externo.
- Se ha optimizado la alineación de párrafos y el tamaño de fuente (12pt) para que coincida con el formato oficial.

---

© 2026 - CEPREUNA Puno
