# RedEMC - Prueba de desarrollo

- **Demo en Railway:** https://redemc-prueba-production.up.railway.app
- **Documentación con Swagger:** https://redemc-prueba-production.up.railway.app/api/swagger *(controlado por `HABILITAR_SWAGGER=true`)*

---

## Estructura del proyecto

```text
RedEMC-Prueba/
├── src/
│   ├── api/
│   │   ├── Controllers/
│   │   │   └── PersonasController.php
│   │   ├── Domain/
│   │   │   └── Persona.php
│   │   ├── Logic/
│   │   │   └── PersonasLogic.php
│   │   ├── Repositories/
│   │   │   └── PersonasRepository.php
│   │   ├── Database.php
│   │   ├── index.php
│   │   ├── openapi.json
│   │   └── swagger.html
│   ├── database/
│   │   └── schema.sql
│   └── frontend/
│       ├── scripts/
│       │   └── index.js
│       ├── styles/
│       │   └── index.css
│       └── index.html
├── .env              # Local, ignorado por Git (crear desde .env.example)
├── .env.example
├── .gitignore
├── Dockerfile
├── PROCESO_IA.md
└── README.md
```

---

## Endpoints de la API

- `GET /api/health` — Verifica que la API esté operativa.
- `GET /api/personas?pagina=1` — Devuelve el listado paginado ordenado del más reciente al más antiguo.
- `POST /api/personas` — Valida y registra una nueva persona (`201`, `422` por error de formato/campo vacío, `409` por duplicado).
- `GET /api/swagger` — Interfaz interactiva de Swagger UI (`openapi.json`), habilitada solo si `HABILITAR_SWAGGER=true` en las variables de entorno.

---

## Variables de entorno

Copiar `.env.example` a `.env` para desarrollo local:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=redemc_prueba
DB_USER=root
DB_PASS=
LIMITE_POR_PAGINA=8
HABILITAR_SWAGGER=true
CORS_ORIGIN=
```

En producción (Railway), `Database.php` toma automáticamente `MYSQL_URL` o las variables `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER` y `MYSQLPASSWORD` sin necesidad de subir `.env`. Además, `HABILITAR_SWAGGER` queda desactivado salvo que se configure en `true`, como lo está ahora mismo. `CORS_ORIGIN` solo se define si se necesita permitir otro origen externo.

---

## Seguridad

- **Credenciales fuera del código:** la contraseña y los datos de conexión nunca se suben al repositorio (`.env` está excluido por `.gitignore`) y en producción se leen desde variables privadas de Railway.
- **Protección contra inyección de código:** todas las consultas a MySQL usan consultas preparadas con `PDO` (para evitar SQL Injection) y el frontend escapa cualquier texto antes de dibujarlo en pantalla (para evitar XSS).
- **Validación sin depender del navegador:** la validación en frontend mejora la experiencia de uso, pero nunca se confía solo en atributos HTML como `required` ni en JavaScript (que pueden alterarse con F12/DevTools o saltarse enviando peticiones directas); el servidor revalida todo desde cero con expresiones regulares junto con índices únicos en MySQL.
- **Acceso restringido al servidor y la API:** todas las peticiones a `/api` pasan únicamente por `index.php` sin exponer los archivos internos, Apache bloquea el listado de carpetas públicas, la API acepta por defecto solo peticiones desde el propio dominio (`CORS_ORIGIN`) y Swagger se controla mediante `HABILITAR_SWAGGER` para poder apagarlo fácilmente en entornos productivos (actualmente activo con `HABILITAR_SWAGGER=true` para facilitar la revisión).
- **Sin fuga de información interna:** el servidor oculta las versiones exactas de Apache y PHP, agrega cabeceras básicas de protección en las respuestas y captura cualquier fallo inesperado devolviendo mensajes genéricos sin mostrar trazas ni detalles de la base.

---

## Cómo se deployó en Railway

1. **Repositorio en GitHub:** Se subió el proyecto a un repositorio público incluyendo el `Dockerfile` en la raíz que empaqueta PHP 8.2 + Apache (con `pdo_mysql`, `mod_rewrite` y `mod_headers`), sirve `src/frontend` en `/` y enruta `/api/*` hacia `src/api/index.php`.
2. **Proyecto y base de datos en Railway:**
   - En [railway.com](https://railway.com) se creó un nuevo proyecto desde el repositorio de GitHub (`Deploy from GitHub repo`), detectando automáticamente el `Dockerfile`.
   - Dentro del mismo proyecto se agregó un servicio **MySQL** (`+ Create` → `Database` → `MySQL`).
3. **Creación de la tabla:**
   - Desde la pestaña **Database** del servicio **MySQL** en Railway se ejecutó el contenido de [`src/database/schema.sql`](src/database/schema.sql) sobre la base `railway` para crear la tabla `personas`.
4. **Configuración de variables de entorno:**
   - En la pestaña **Variables** del servicio de la aplicación se vincularon:
     - `MYSQL_URL` = `${{MySQL.MYSQL_URL}}`
     - `LIMITE_POR_PAGINA` = `8`
     - `HABILITAR_SWAGGER` = `true`
     - `PORT` = `80`
5. **Dominio público y verificación:**
   - En **Settings → Networking → Generate Domain** (puerto `80`) se generó la URL pública.
   - Se verificó de punta a punta ingresando registros desde el navegador, validando formatos y duplicados, recargando la página para confirmar persistencia en MySQL y navegando entre páginas sin refrescar. Se verificó también su funcionamiento en distintos navegadores y la adaptación responsive a diferentes tamaños de pantalla.
