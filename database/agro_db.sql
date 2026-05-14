CREATE DATABASE IF NOT EXISTS agro_db;
USE agro_db;

CREATE TABLE roles (
    id_rol TINYINT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    activo BOOLEAN NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL
);

CREATE TABLE permisos (
    id_permiso SMALLINT PRIMARY KEY,
    codigo VARCHAR(60) NOT NULL UNIQUE,
    descripcion VARCHAR(200) NOT NULL,
    creado_en DATETIME NOT NULL
);

CREATE TABLE roles_permisos (
    id_rol TINYINT NOT NULL,
    id_permiso SMALLINT NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
    FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso)
);

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_rol TINYINT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(30),
    foto_perfil VARCHAR(255) NULL DEFAULT NULL,
    activo BOOLEAN NOT NULL DEFAULT 1,
    intentos_fallidos TINYINT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME,
    ultimo_acceso DATETIME,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_usuarios_rol (id_rol, activo),
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

CREATE TABLE sesiones_usuario (
    id_sesion BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    dispositivo VARCHAR(120),
    ip_origen VARCHAR(45),
    estado VARCHAR(20) NOT NULL DEFAULT 'activa',
    iniciada_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    cerrada_en DATETIME,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE tipos_cultivo (
    id_tipo TINYINT PRIMARY KEY,
    codigo VARCHAR(15) NOT NULL UNIQUE,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    fotografia VARCHAR(255),
    activo BOOLEAN NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL
);

CREATE TABLE variedades (
    id_variedad INT AUTO_INCREMENT PRIMARY KEY,
    id_tipo TINYINT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    dias_cosecha_min SMALLINT NOT NULL,
    dias_cosecha_max SMALLINT NOT NULL,
    dias_cosecha_promedio SMALLINT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL,
    CONSTRAINT uq_variedad_tipo_nombre UNIQUE (id_tipo, nombre),
    FOREIGN KEY (id_tipo) REFERENCES tipos_cultivo(id_tipo)
);

CREATE TABLE tipos_actividad (
    id_tipo_actividad TINYINT PRIMARY KEY,
    codigo VARCHAR(25) NOT NULL UNIQUE,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    dias_recordatorio_previo SMALLINT NOT NULL DEFAULT 1,
    dias_frecuencia_sugerida SMALLINT,
    activo BOOLEAN NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL
);

CREATE TABLE lotes (
    id_lote INT AUTO_INCREMENT PRIMARY KEY,
    identificador CHAR(1) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(200) NOT NULL,
    area_ha DECIMAL(6,2) NOT NULL,
    id_tipo_preferido TINYINT,
    fotografia VARCHAR(255),
    es_alternativo BOOLEAN NOT NULL DEFAULT 0,
    estado VARCHAR(20) NOT NULL DEFAULT 'disponible',
    activo BOOLEAN NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_lotes_estado (estado),
    FOREIGN KEY (id_tipo_preferido) REFERENCES tipos_cultivo(id_tipo)
);

CREATE TABLE usuarios_lotes (
    id_asignacion BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_lote INT NOT NULL,
    asignado_por INT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT 1,
    clave_activa VARCHAR(40),
    creado_en DATETIME NOT NULL,
    CONSTRAINT uq_usuario_lote_activo UNIQUE (clave_activa),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_lote) REFERENCES lotes(id_lote),
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id_usuario)
);

CREATE TABLE cultivos (
    id_cultivo INT AUTO_INCREMENT PRIMARY KEY,
    id_lote INT NOT NULL,
    id_variedad INT NOT NULL,
    id_registrado_por INT NOT NULL,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    estado VARCHAR(20) NOT NULL DEFAULT 'sembrado',
    fecha_siembra DATE NOT NULL,
    fecha_cosecha_estimada DATE NOT NULL,
    fecha_cosecha_real DATE,
    cantidad_cosechada_kg DECIMAL(10,2),
    observaciones TEXT,
    fotografia VARCHAR(500) NULL DEFAULT NULL,
    activo_en_lote INT,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    CONSTRAINT uq_un_cultivo_activo_por_lote UNIQUE (activo_en_lote),
    INDEX idx_cultivos_lote (id_lote),
    INDEX idx_cultivos_estado (estado),
    INDEX idx_cultivos_cosecha (fecha_cosecha_estimada),
    FOREIGN KEY (id_lote) REFERENCES lotes(id_lote),
    FOREIGN KEY (id_variedad) REFERENCES variedades(id_variedad),
    FOREIGN KEY (id_registrado_por) REFERENCES usuarios(id_usuario)
);

CREATE TABLE actividades (
    id_actividad BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_cultivo INT NOT NULL,
    id_tipo_actividad TINYINT NOT NULL,
    id_creado_por INT NOT NULL,
    id_asignado_a INT,
    id_ejecutado_por INT,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    fecha_programada DATE,
    fecha_ejecucion DATE,
    descripcion VARCHAR(500) NOT NULL,
    observaciones TEXT,
    fotografia VARCHAR(500) NULL DEFAULT NULL,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_actividades_cultivo (id_cultivo),
    INDEX idx_actividades_asignado (id_asignado_a, estado),
    INDEX idx_actividades_programada (fecha_programada, estado),
    FOREIGN KEY (id_cultivo) REFERENCES cultivos(id_cultivo),
    FOREIGN KEY (id_tipo_actividad) REFERENCES tipos_actividad(id_tipo_actividad),
    FOREIGN KEY (id_creado_por) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_asignado_a) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_ejecutado_por) REFERENCES usuarios(id_usuario)
);

CREATE TABLE notificaciones (
    id_notificacion BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_cultivo INT,
    id_actividad BIGINT,
    tipo VARCHAR(30) NOT NULL,
    prioridad VARCHAR(10) NOT NULL DEFAULT 'media',
    titulo VARCHAR(150) NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    leida BOOLEAN NOT NULL DEFAULT 0,
    leida_en DATETIME,
    programada_para DATETIME,
    creada_en DATETIME NOT NULL,
    INDEX idx_notificaciones_usuario (id_usuario, leida, prioridad),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_cultivo) REFERENCES cultivos(id_cultivo),
    FOREIGN KEY (id_actividad) REFERENCES actividades(id_actividad)
);

CREATE TABLE sincronizaciones (
    id_sincronizacion BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    dispositivo VARCHAR(120) NOT NULL,
    tipo VARCHAR(15) NOT NULL DEFAULT 'automatica',
    estado VARCHAR(15) NOT NULL DEFAULT 'pendiente',
    registros_subidos INT NOT NULL DEFAULT 0,
    registros_descargados INT NOT NULL DEFAULT 0,
    iniciada_en DATETIME NOT NULL,
    finalizada_en DATETIME,
    mensaje_error VARCHAR(500),
    INDEX idx_sincronizaciones_usuario (id_usuario, estado),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE auditoria_cambios (
    id_auditoria BIGINT AUTO_INCREMENT PRIMARY KEY,
    tabla_afectada VARCHAR(64) NOT NULL,
    id_registro VARCHAR(64) NOT NULL,
    accion VARCHAR(10) NOT NULL,
    realizado_por INT,
    datos_antes JSON,
    datos_despues JSON,
    fecha_evento DATETIME NOT NULL,
    ip_origen VARCHAR(45),
    FOREIGN KEY (realizado_por) REFERENCES usuarios(id_usuario)
);

-- ─────────────────────────────────────────────
-- DATOS: roles
-- ─────────────────────────────────────────────
INSERT INTO roles (id_rol, nombre, descripcion, activo, creado_en) VALUES
(1, 'Administrador', 'Acceso total al sistema', 1, NOW()),
(2, 'Trabajador',    'Acceso operativo a sus lotes y actividades', 1, NOW());

-- ─────────────────────────────────────────────
-- DATOS: permisos
-- ─────────────────────────────────────────────
INSERT INTO permisos (id_permiso, codigo, descripcion, creado_en) VALUES
(1,  'lotes.crear',          'Crear nuevos lotes',                    NOW()),
(2,  'lotes.editar',         'Editar lotes existentes',               NOW()),
(3,  'lotes.eliminar',       'Eliminar lotes',                        NOW()),
(4,  'cultivos.crear',       'Registrar nuevos cultivos',             NOW()),
(5,  'cultivos.editar',      'Editar cultivos existentes',            NOW()),
(6,  'cultivos.eliminar',    'Eliminar cultivos',                     NOW()),
(7,  'actividades.crear',    'Crear actividades',                     NOW()),
(8,  'actividades.editar',   'Editar actividades',                    NOW()),
(9,  'actividades.eliminar', 'Eliminar actividades',                  NOW()),
(10, 'reportes.ver',         'Ver y generar reportes',                NOW()),
(11, 'usuarios.gestionar',   'Crear y editar usuarios',               NOW()),
(12, 'fotos.eliminar',       'Eliminar fotografías',                  NOW());

-- ─────────────────────────────────────────────
-- DATOS: roles_permisos (admin tiene todos; trabajador solo lectura/fotos)
-- ─────────────────────────────────────────────
INSERT INTO roles_permisos (id_rol, id_permiso) VALUES
-- Administrador: todos los permisos
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),
-- Trabajador: puede subir/eliminar sus fotos y ver reportes
(2,10),(2,12);

-- ─────────────────────────────────────────────
-- DATOS: tipos de cultivo
-- ─────────────────────────────────────────────
INSERT INTO tipos_cultivo (id_tipo, codigo, nombre, descripcion, activo, creado_en) VALUES
(1, 'CAFE',  'Café',  'Cultivo de café en sus distintas variedades', 1, NOW()),
(2, 'MAIZ',  'Maíz',  'Cultivo de maíz amarillo y dulce',            1, NOW()),
(3, 'PLATANO','Plátano','Cultivo de plátano y banano',               1, NOW()),
(4, 'YUCA',  'Yuca',  'Cultivo de yuca blanca y amarilla',           1, NOW());

-- ─────────────────────────────────────────────
-- DATOS: variedades
-- (id_tipo, nombre, dias_min, dias_max, dias_promedio)
-- ─────────────────────────────────────────────
INSERT INTO variedades (id_tipo, nombre, dias_cosecha_min, dias_cosecha_max, dias_cosecha_promedio, activo, creado_en) VALUES
-- Café
(1, 'Arábica',         240, 300, 270, 1, NOW()),
(1, 'Castillo',        240, 300, 270, 1, NOW()),
(1, 'Colombia',        240, 300, 270, 1, NOW()),
(1, 'Caturra',         240, 300, 270, 1, NOW()),
(1, 'Tabi',            270, 330, 300, 1, NOW()),
-- Maíz
(2, 'Maíz Amarillo',   100, 130, 115, 1, NOW()),
(2, 'Maíz Dulce',       80, 100,  90, 1, NOW()),
(2, 'Maíz Blanco',     100, 130, 115, 1, NOW()),
(2, 'Maíz Pira',        90, 120, 105, 1, NOW()),
-- Plátano
(3, 'Dominico Hartón', 270, 330, 300, 1, NOW()),
(3, 'Cachaco',         270, 330, 300, 1, NOW()),
(3, 'Banano Cavendish',270, 300, 285, 1, NOW()),
-- Yuca
(4, 'Yuca Blanca',     240, 300, 270, 1, NOW()),
(4, 'Yuca Amarilla',   270, 330, 300, 1, NOW());

-- ─────────────────────────────────────────────
-- DATOS: tipos de actividad
-- ─────────────────────────────────────────────
INSERT INTO tipos_actividad (id_tipo_actividad, codigo, nombre, descripcion, dias_recordatorio_previo, dias_frecuencia_sugerida, activo, creado_en) VALUES
(1, 'RIEGO',         'Riego',         'Aplicación de agua al cultivo',              1,  7, 1, NOW()),
(2, 'FERTILIZACION', 'Fertilización', 'Aplicación de fertilizantes o abonos',       2, 30, 1, NOW()),
(3, 'PODA',          'Poda',          'Corte y mantenimiento de ramas',             2, 60, 1, NOW()),
(4, 'FUMIGACION',    'Fumigación',    'Control de plagas y enfermedades',           1, 15, 1, NOW()),
(5, 'COSECHA',       'Cosecha',       'Recolección del producto',                   3,  0, 1, NOW()),
(6, 'SIEMBRA',       'Siembra',       'Plantación de semillas o plántulas',         1,  0, 1, NOW()),
(7, 'DESHIERBE',     'Deshierbe',     'Eliminación de malezas',                     1, 21, 1, NOW()),
(8, 'MONITOREO',     'Monitoreo',     'Revisión y seguimiento del estado del cultivo', 1, 7, 1, NOW());


-- ─────────────────────────────────────────────
-- DATOS: usuarios de prueba
-- Admin:      sergio@correo.com  / sergio123
-- Trabajador: didier@correo.com  / didier123
-- ─────────────────────────────────────────────
INSERT INTO usuarios (id_rol, nombre, correo, contrasena_hash, telefono, activo, intentos_fallidos, creado_en, actualizado_en)
VALUES
(1, 'Sergio', 'sergio@correo.com', '$2y$10$7VgpyWl4011URk2K5rVL.eokrH6r45ZC2qVwaEKBgbYUN6OZzABGy', '0000000000', 1, 0, NOW(), NOW()),
(2, 'Didier', 'didier@correo.com', '$2y$10$fidhA1al0niiXdp.76h4hpOcQ.g7VCxHvER6rmyCq8Ndyvhva1bfze', '0000000000', 1, 0, NOW(), NOW());
