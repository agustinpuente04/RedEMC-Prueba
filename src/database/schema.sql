-- Esquema de la base de datos

CREATE TABLE IF NOT EXISTS personas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(9) NULL DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_personas_email (email),
    UNIQUE KEY uq_personas_telefono (telefono),
    KEY idx_personas_orden (fecha_creacion DESC, id DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
