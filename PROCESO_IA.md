# Proceso de trabajo con IA

## 1. Herramientas de IA utilizadas

Utilicé dos herramientas de forma complementaria, siempre revisando, probando y ajustando cada propuesta:

- **ChatGPT:** consultas más teóricas, definición de la arquitectura y estructura de carpetas, así como dudas generales de infraestructura y despliegue.
- **Antigravity (con Gemini):** integrada directamente en el entorno de desarrollo para programar, iterar y verificar cada parte del proyecto:
  - **API en PHP (`src/api/`):** arquitectura por capas (`Controllers`, `Logic`, `Repositories`, `Domain`, `Database`) con `PDO` y prepared statements.
  - **Base de datos y entorno local (`src/database/schema.sql` y Docker):** definición de la tabla `personas`, atributos únicos y configuración de Docker Compose fuera del repositorio para probar PHP 8.2 + MySQL 8 sin instalar servicios sueltos en Windows.
  - **Documentación de API (`/api/swagger`):** especificación `openapi.json` e interfaz Swagger UI por CDN para probar los endpoints antes de pasar al frontend.
  - **Frontend (`src/frontend/`):** maquetado 100% con `div`s usando Bootstrap 5, estilos CSS propios sin imágenes externas (incluyendo íconos y bandera uruguaya en SVG inline), AJAX, validaciones por regex y paginación dinámica.
  - **Despliegue (`Dockerfile` y seguridad):** configuración de Apache (`Alias`, `mod_rewrite`, `mod_headers`, `Options -Indexes`), ocultamiento de versiones y soporte para Railway.

---

## 2. Prompts clave

1. **Arquitectura simple, configuración en `.env` y consistencia en español:**
   > *"en la lógica de personas sacá los límites extra que no pide la letra y pasá el límite de 8 registros por página al .env para que sea configurable. mantené el código simple, modificable y escalable sin sobreingeniería y unificá todos los nombres, variables y respuestas json en español"*

2. **Paginación con salto directo y manejo de casos borde:**
   > *"en la paginación sumá un input para ir directo a un número de página, si se pone 0 o un negativo te manda a la 1, si supera el total te manda a la última y si se escribe algo inválido vuelve a la página actual sin recargar"*

3. **Revisión de seguridad para producción:**
   > *"revisá qué medidas de seguridad concretas faltan para subir esto a railway sin complicar el proyecto, no hay que depender de validaciones HTML como required (que se editan con f12) y quiero controlar swagger con una variable de entorno"*

---

## 3. Propuestas de la IA que rechacé o corregí

- **Sobreingeniería y mezcla de idiomas en la API:** en la primera versión del backend la IA generó `Persona.php` con constructor abreviado, propiedades anulables innecesarias (`id` opcional), constantes extra de límites no pedidos por la letra (`MAX_LIMIT`, `MAX_NOMBRE_LENGTH`, `MAX_EMAIL_LENGTH`) y nombres mezclados en inglés y español. Le pedí que hiciera obligatorio el `id` en `Persona`, usara métodos claros en español, quitara los límites inventados y dejara el `8` por defecto en `.env` (`LIMITE_POR_PAGINA`).
- **Uso de XAMPP para probar en local:** la IA sugirió levantar MySQL y PHP con XAMPP porque ya estaba en la máquina. Lo rechacé por considerarlo obsoleto para este flujo y opté por levantar **Docker** con `docker-compose.yml` fuera de la carpeta del repositorio.
- **Seguridad por defecto para producción:** la primera versión dejaba `CORS_ORIGIN` abierto con `*` y `/api/swagger` siempre público; lo ajustamos para que CORS funcione cerrado al mismo origen por defecto y Swagger pase a controlarse mediante la variable de entorno `HABILITAR_SWAGGER`, además de que Apache y PHP oculten versiones y listados de carpetas (`Options -Indexes`).

---

## 4. Que aprendí realizando la prueba

- **Implementación de AJAX:** repasé cómo implementar peticiones asíncronas con `$.ajax` para enviar y recibir JSON sin recargar la página, manejando respuestas de éxito (`200`, `201`) y errores de validación o duplicado (`422`, `409`) desde la API PHP para actualizar dinámicamente solo el listado y la paginación.
- **Funcionamiento de Railway en producción:** aprendí a operar la plataforma de punta a punta, vincular un repositorio con `Dockerfile` junto a un servicio MySQL dentro del mismo proyecto, crear las tablas desde la pestaña **Database**, enlazar los servicios con `${{MySQL.MYSQL_URL}}`, exponer el dominio público por el puerto correcto y usar *Serverless / App Sleeping* para evitar consumo cuando no hay tráfico.
- **Buenas prácticas básicas de seguridad web:** mantener `.env` fuera del repositorio, entender que `CORS_ORIGIN` restringe sitios externos sin afectar la compatibilidad entre navegadores, ocultar versiones del servidor, bloquear listados de directorios y sumar cabeceras como `Referrer-Policy`, `X-Frame-Options` y `X-Content-Type-Options`.
