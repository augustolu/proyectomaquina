-- =============================================================================
-- ESQUEMA DE BASE DE DATOS: Artesanos.com
-- Arquitectura: WAMP (Windows, Apache, MySQL, PHP)
-- Especialista: Senior Full Stack Developer
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. CREACIÓN DE LA BASE DE DATOS
CREATE DATABASE IF NOT EXISTS artesanos_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE artesanos_db;

-- =============================================================================
-- 2. TABLA: intereses
-- =============================================================================
CREATE TABLE intereses (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nombre (nombre)
) ENGINE=InnoDB COMMENT='Catálogo maestro de intereses de artesanos.';

-- =============================================================================
-- 3. TABLA: usuarios
-- =============================================================================
CREATE TABLE usuarios (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username                VARCHAR(50)     NOT NULL,
    email                   VARCHAR(255)    NOT NULL,
    password_hash           VARCHAR(255)    NOT NULL COMMENT 'Hash seguro (bcrypt/argon2)',
    nombre                  VARCHAR(100)    NOT NULL,
    apellido                VARCHAR(100)    NOT NULL,
    fecha_nacimiento        DATE            NULL,
    biografia               TEXT            NULL,
    foto_perfil_actual_id   INT UNSIGNED    NULL COMMENT 'FK a historial_fotos_perfil',
    es_cuenta_privada       TINYINT(1)      NOT NULL DEFAULT 0,
    fecha_registro          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB COMMENT='Información central del usuario y acceso.';

-- =============================================================================
-- 4. TABLA: usuario_intereses
-- =============================================================================
CREATE TABLE usuario_intereses (
    usuario_id  INT UNSIGNED    NOT NULL,
    interes_id  INT UNSIGNED    NOT NULL,
    PRIMARY KEY (usuario_id, interes_id),
    CONSTRAINT fk_ui_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ui_interes FOREIGN KEY (interes_id) REFERENCES intereses(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Relación entre usuarios y sus intereses declarados.';

-- =============================================================================
-- 5. TABLA: albumes
-- =============================================================================
CREATE TABLE albumes (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED    NOT NULL,
    titulo      VARCHAR(200)    NOT NULL,
    descripcion TEXT            NULL,
    tipo        ENUM('normal', 'perfil') NOT NULL DEFAULT 'normal',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_album_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Contenedores de imágenes por usuario.';

-- =============================================================================
-- 6. TABLA: imagenes
-- =============================================================================
CREATE TABLE imagenes (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    album_id        INT UNSIGNED    NOT NULL,
    titulo          VARCHAR(200)    NULL COMMENT 'Título opcional',
    url_almacen     VARCHAR(500)    NOT NULL COMMENT 'Ruta al asset',
    mime_type       VARCHAR(100)    NOT NULL,
    tamano_bytes    INT UNSIGNED    NOT NULL,
    privacidad      ENUM('publico', 'privado') DEFAULT 'publico',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_album (album_id),
    CONSTRAINT fk_img_album FOREIGN KEY (album_id) REFERENCES albumes(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Imágenes individuales de la red social.';

-- =============================================================================
-- 7. TABLA: historial_fotos_perfil
-- =============================================================================
CREATE TABLE historial_fotos_perfil (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED    NOT NULL,
    imagen_id   INT UNSIGNED    NOT NULL,
    fecha_desde TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    fecha_hasta TIMESTAMP       NULL COMMENT 'NULL si es la foto de perfil actual',
    PRIMARY KEY (id),
    CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_imagen  FOREIGN KEY (imagen_id)  REFERENCES imagenes(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Registro histórico de imágenes de perfil.';

-- =============================================================================
-- AGREGAR FK DIFERIDA PARA FOTO DE PERFIL ACTUAL
-- =============================================================================
ALTER TABLE usuarios
    ADD CONSTRAINT fk_user_foto_perfil
    FOREIGN KEY (foto_perfil_actual_id) REFERENCES historial_fotos_perfil(id)
    ON DELETE SET NULL;

-- =============================================================================
-- 8. TABLA: interacciones_comentarios
-- =============================================================================
CREATE TABLE interacciones_comentarios (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    imagen_id       INT UNSIGNED    NOT NULL,
    usuario_id      INT UNSIGNED    NOT NULL,
    comentario_id_padre INT UNSIGNED NULL COMMENT 'Para respuestas anidadas',
    contenido       TEXT            NOT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_imagen (imagen_id),
    CONSTRAINT fk_com_imagen FOREIGN KEY (imagen_id) REFERENCES imagenes(id) ON DELETE CASCADE,
    CONSTRAINT fk_com_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_com_padre  FOREIGN KEY (comentario_id_padre) REFERENCES interacciones_comentarios(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Historial de comentarios e hilos de respuesta.';

-- =============================================================================
-- 9. TABLA: interacciones_likes
-- =============================================================================
CREATE TABLE interacciones_likes (
    imagen_id   INT UNSIGNED    NOT NULL,
    usuario_id  INT UNSIGNED    NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (imagen_id, usuario_id),
    CONSTRAINT fk_like_imagen FOREIGN KEY (imagen_id) REFERENCES imagenes(id) ON DELETE CASCADE,
    CONSTRAINT fk_like_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Likes de usuarios en imágenes.';

-- =============================================================================
-- 10. TABLA: seguidores
-- =============================================================================
CREATE TABLE seguidores (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    seguidor_id     INT UNSIGNED    NOT NULL,
    seguido_id      INT UNSIGNED    NOT NULL,
    estado          ENUM('pendiente', 'aceptada', 'rechazada') DEFAULT 'pendiente',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seguimiento (seguidor_id, seguido_id),
    CONSTRAINT fk_seguidor FOREIGN KEY (seguidor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_seguido  FOREIGN KEY (seguido_id)  REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT chk_no_seguirse_mismo CHECK (seguidor_id <> seguido_id)
) ENGINE=InnoDB COMMENT='Red de contactos unidireccional con aprobación.';

SET FOREIGN_KEY_CHECKS = 1;

-- SENTENCIAS ALTER TABLE (Como referencia para bases de datos existentes)
/*
ALTER TABLE usuarios ADD COLUMN foto_perfil_actual_id INT UNSIGNED NULL AFTER biografia;
ALTER TABLE usuarios ADD COLUMN es_cuenta_privada TINYINT(1) NOT NULL DEFAULT 0 AFTER foto_perfil_actual_id;
ALTER TABLE albumes ADD COLUMN tipo ENUM('normal', 'perfil') NOT NULL DEFAULT 'normal' AFTER descripcion;
ALTER TABLE imagenes CHANGE COLUMN url url_almacen VARCHAR(500) NOT NULL;
ALTER TABLE imagenes ADD COLUMN mime_type VARCHAR(100) NOT NULL AFTER url_almacen;
ALTER TABLE imagenes ADD COLUMN tamano_bytes INT UNSIGNED NOT NULL AFTER mime_type;
ALTER TABLE usuarios ADD CONSTRAINT fk_user_foto_perfil FOREIGN KEY (foto_perfil_actual_id) REFERENCES historial_fotos_perfil(id) ON DELETE SET NULL;
*/
