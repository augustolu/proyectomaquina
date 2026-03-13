-- =============================================================================
-- SCHEMA NORMALIZADO - RED SOCIAL DE FOTOGRAFÍA
-- MySQL 8.0+
-- Autor: Generado automáticamente
-- Fecha: 2026-03-07
--
-- DECISIONES DE DISEÑO GENERALES:
--   - Todas las PKs usan INT UNSIGNED AUTO_INCREMENT para eficiencia en índices B-Tree.
--   - Se usa ENUM para estados con dominio pequeño y fijo (privacidad, solicitudes).
--   - Los "soft deletes" se implementan con deleted_at (timestamp nullable) en vez
--     de borrado físico, preservando integridad referencial e historial.
--   - Se definen ON DELETE RESTRICT por defecto para evitar borrados en cascada
--     accidentales; se usa ON DELETE CASCADE solo donde es semánticamente correcto
--     (ej: borrar un álbum borra sus imágenes).
-- =============================================================================

-- Crear y seleccionar la base de datos
CREATE DATABASE IF NOT EXISTS red_social_fotos
  CHARACTER SET utf8mb4      -- Soporte completo Unicode (emojis, etc.)
  COLLATE utf8mb4_unicode_ci;

USE red_social_fotos;

-- =============================================================================
-- TABLA: intereses
-- Catálogo normalizado de intereses (3NF). Evita redundancia al no almacenar
-- el texto del interés directamente en la tabla de usuarios.
-- =============================================================================
CREATE TABLE intereses (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    descripcion VARCHAR(255)    NULL,
    CONSTRAINT pk_intereses PRIMARY KEY (id),
    CONSTRAINT uq_intereses_nombre UNIQUE (nombre)
) ENGINE=InnoDB
  COMMENT='Catálogo maestro de intereses disponibles para los usuarios.';


-- =============================================================================
-- TABLA: usuarios
-- Almacena los datos personales y de acceso de cada usuario.
-- La foto de perfil activa se referencia con foto_perfil_actual_id como FK
-- diferida (se agrega con ALTER TABLE más abajo para evitar dependencia circular
-- con la tabla historial_fotos_perfil).
-- =============================================================================
CREATE TABLE usuarios (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username                VARCHAR(50)     NOT NULL  COMMENT 'Nombre de usuario único, usado en URLs públicas.',
    email                   VARCHAR(255)    NOT NULL,
    password_hash           VARCHAR(255)    NOT NULL  COMMENT 'Hash bcrypt/argon2 de la contraseña. NUNCA almacenar en texto plano.',
    nombre                  VARCHAR(100)    NOT NULL,
    apellido                VARCHAR(100)    NOT NULL,
    fecha_nacimiento        DATE            NULL,
    biografia               TEXT            NULL,
    -- La FK a la foto de perfil activa se añade después (dependencia circular)
    foto_perfil_actual_id   INT UNSIGNED    NULL      COMMENT 'FK a historial_fotos_perfil. NULL si no tiene foto.',
    es_cuenta_privada       TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = cuenta privada; los seguidores deben ser aprobados.',
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL     COMMENT 'Soft delete: si no es NULL el usuario está inactivo.',
    CONSTRAINT pk_usuarios PRIMARY KEY (id),
    CONSTRAINT uq_usuarios_username UNIQUE (username),
    CONSTRAINT uq_usuarios_email    UNIQUE (email)
) ENGINE=InnoDB
  COMMENT='Datos de autenticación e información personal de cada usuario.';

-- Índice para búsquedas por nombre completo
CREATE INDEX idx_usuarios_nombre ON usuarios (nombre, apellido);


-- =============================================================================
-- TABLA: usuario_intereses
-- Tabla de unión (N:M) entre usuarios e intereses.
-- Permite que un usuario tenga múltiples intereses sin desnormalizar usuarios.
-- =============================================================================
CREATE TABLE usuario_intereses (
    usuario_id  INT UNSIGNED    NOT NULL,
    interes_id  INT UNSIGNED    NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_usuario_intereses PRIMARY KEY (usuario_id, interes_id),
    CONSTRAINT fk_ui_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ui_interes FOREIGN KEY (interes_id)
        REFERENCES intereses(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Relación N:M entre usuarios e intereses del catálogo.';

-- Índice inverso para consultas tipo "¿quién tiene este interés?"
CREATE INDEX idx_ui_interes ON usuario_intereses (interes_id);


-- =============================================================================
-- TABLA: albumes
-- Un álbum pertenece a un usuario. El título es obligatorio.
-- Se reserva un álbum especial de sistema (tipo='perfil') para las fotos
-- de perfil, desacoplando la lógica sin necesitar una tabla de imágenes separada.
-- =============================================================================
CREATE TABLE albumes (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED    NOT NULL,
    titulo      VARCHAR(200)    NOT NULL  COMMENT 'Título obligatorio del álbum.',
    descripcion TEXT            NULL,
    tipo        ENUM('normal', 'perfil')
                                NOT NULL DEFAULT 'normal'
                                COMMENT 'El tipo "perfil" es gestionado por el sistema para fotos de perfil.',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP       NULL,
    CONSTRAINT pk_albumes PRIMARY KEY (id),
    CONSTRAINT fk_albumes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Colecciones de imágenes organizadas por el usuario.';

CREATE INDEX idx_albumes_usuario ON albumes (usuario_id);


-- =============================================================================
-- TABLA: imagenes
-- Cada imagen pertenece a un álbum. El título es opcional.
-- La privacidad controla la visibilidad (pública o privada).
-- Las imágenes de perfil residen en el álbum de tipo='perfil' del usuario.
-- =============================================================================
CREATE TABLE imagenes (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    album_id        INT UNSIGNED    NOT NULL  COMMENT 'Toda imagen pertenece a un álbum (incluso las de perfil).',
    titulo          VARCHAR(200)    NULL      COMMENT 'Título opcional de la imagen.',
    descripcion     TEXT            NULL,
    url_almacen     VARCHAR(500)    NOT NULL  COMMENT 'Ruta o URL al archivo en el sistema de almacenamiento (S3, filesystem, etc.).',
    mime_type       VARCHAR(100)    NOT NULL  COMMENT 'Ej: image/jpeg, image/png.',
    tamano_bytes    INT UNSIGNED    NULL      COMMENT 'Tamaño del archivo en bytes.',
    ancho_px        SMALLINT UNSIGNED NULL,
    alto_px         SMALLINT UNSIGNED NULL,
    privacidad      ENUM('publico', 'privado')
                                    NOT NULL DEFAULT 'publico'
                                    COMMENT 'Controla quién puede ver la imagen.',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,
    CONSTRAINT pk_imagenes PRIMARY KEY (id),
    CONSTRAINT fk_imagenes_album FOREIGN KEY (album_id)
        REFERENCES albumes(id) ON DELETE CASCADE ON UPDATE CASCADE
        COMMENT 'Si se elimina el álbum, se eliminan sus imágenes.'
) ENGINE=InnoDB
  COMMENT='Imágenes subidas por los usuarios, organizadas en álbumes.';

CREATE INDEX idx_imagenes_album     ON imagenes (album_id);
CREATE INDEX idx_imagenes_privacidad ON imagenes (privacidad);  -- Filtrado frecuente por privacidad
CREATE INDEX idx_imagenes_created   ON imagenes (created_at DESC); -- Feeds cronológicos


-- =============================================================================
-- TABLA: historial_fotos_perfil
-- Registra cada vez que un usuario cambia su foto de perfil, permitiendo
-- rastrear el historial completo.
-- NOTA: La imagen de perfil reside en el álbum de tipo='perfil' del usuario,
--       por lo que imagen_id apunta a la tabla imagenes.
-- =============================================================================
CREATE TABLE historial_fotos_perfil (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED    NOT NULL,
    imagen_id   INT UNSIGNED    NOT NULL  COMMENT 'Referencia a la imagen usada como foto de perfil.',
    fecha_desde TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Momento en que se estableció esta foto.',
    fecha_hasta TIMESTAMP       NULL      COMMENT 'NULL indica que es la foto activa actualmente.',
    CONSTRAINT pk_historial_fotos_perfil PRIMARY KEY (id),
    CONSTRAINT fk_hfp_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_hfp_imagen FOREIGN KEY (imagen_id)
        REFERENCES imagenes(id) ON DELETE RESTRICT ON UPDATE CASCADE
        COMMENT 'Restringir borrado: no se puede eliminar una imagen que fue (o es) foto de perfil.'
) ENGINE=InnoDB
  COMMENT='Historial cronológico de fotos de perfil de cada usuario.';

CREATE INDEX idx_hfp_usuario ON historial_fotos_perfil (usuario_id, fecha_desde DESC);


-- =============================================================================
-- FK DIFERIDA: foto_perfil_actual_id en usuarios
-- Se añade aquí para resolver la dependencia circular:
--   usuarios → historial_fotos_perfil → imagenes
-- =============================================================================
ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_foto_perfil_actual
        FOREIGN KEY (foto_perfil_actual_id)
        REFERENCES historial_fotos_perfil(id)
        ON DELETE SET NULL ON UPDATE CASCADE
    COMMENT 'Apunta al registro activo del historial; SET NULL si se elimina el registro de historial.';


-- =============================================================================
-- TABLA: seguidores
-- Relación unidireccional: un usuario (seguidor) sigue a otro (seguido).
-- El estado gestiona el flujo de aprobación en cuentas privadas.
-- =============================================================================
CREATE TABLE seguidores (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    seguidor_id     INT UNSIGNED    NOT NULL  COMMENT 'Usuario que envía la solicitud de seguimiento.',
    seguido_id      INT UNSIGNED    NOT NULL  COMMENT 'Usuario al que se desea seguir.',
    estado          ENUM('pendiente', 'aceptada', 'rechazada')
                                    NOT NULL DEFAULT 'pendiente'
                                    COMMENT 'Para cuentas públicas, se puede aceptar automáticamente.',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_seguidores PRIMARY KEY (id),
    -- Un par (seguidor, seguido) es único: no se puede seguir dos veces a la misma persona
    CONSTRAINT uq_seguidores UNIQUE (seguidor_id, seguido_id),
    CONSTRAINT fk_seg_seguidor FOREIGN KEY (seguidor_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seg_seguido FOREIGN KEY (seguido_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    -- Un usuario no puede seguirse a sí mismo (constraint a nivel de aplicación, documentado aquí)
    CONSTRAINT chk_seguidores_no_auto CHECK (seguidor_id <> seguido_id)
) ENGINE=InnoDB
  COMMENT='Relación de seguimiento unidireccional entre usuarios con flujo de aprobación.';

-- Índice para consultas "¿a quién sigo?" y "¿quién me sigue?"
CREATE INDEX idx_seg_seguidor ON seguidores (seguidor_id, estado);
CREATE INDEX idx_seg_seguido  ON seguidores (seguido_id,  estado);


-- =============================================================================
-- TABLA: comentarios
-- Historial de comentarios en imágenes. Soporta respuestas anidadas con
-- comentario_padre_id (jerarquía de un nivel, extensible).
-- =============================================================================
CREATE TABLE comentarios (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    imagen_id           INT UNSIGNED    NOT NULL,
    usuario_id          INT UNSIGNED    NOT NULL  COMMENT 'Autor del comentario.',
    comentario_padre_id INT UNSIGNED    NULL      COMMENT 'NULL = comentario raíz; NOT NULL = respuesta a otro comentario.',
    contenido           TEXT            NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP       NULL      COMMENT 'Soft delete para moderar sin perder el hilo.',
    CONSTRAINT pk_comentarios PRIMARY KEY (id),
    CONSTRAINT fk_com_imagen FOREIGN KEY (imagen_id)
        REFERENCES imagenes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_com_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_com_padre FOREIGN KEY (comentario_padre_id)
        REFERENCES comentarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Comentarios de usuarios en imágenes, con soporte para respuestas anidadas.';

CREATE INDEX idx_com_imagen   ON comentarios (imagen_id,           deleted_at);
CREATE INDEX idx_com_usuario  ON comentarios (usuario_id);
CREATE INDEX idx_com_padre    ON comentarios (comentario_padre_id);


-- =============================================================================
-- TABLA: likes
-- Registro de "me gusta" en imágenes. La combinación (imagen_id, usuario_id)
-- es única para garantizar que un usuario solo pueda dar un like por imagen.
-- =============================================================================
CREATE TABLE likes (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    imagen_id   INT UNSIGNED    NOT NULL,
    usuario_id  INT UNSIGNED    NOT NULL  COMMENT 'Usuario que dio el like.',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_likes PRIMARY KEY (id),
    -- Garantía de un solo like por usuario por imagen
    CONSTRAINT uq_likes UNIQUE (imagen_id, usuario_id),
    CONSTRAINT fk_likes_imagen FOREIGN KEY (imagen_id)
        REFERENCES imagenes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_likes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Registro de "me gusta" de usuarios en imágenes. Máximo uno por usuario por imagen.';

-- Índice para contar likes de un usuario (perfil de actividad)
CREATE INDEX idx_likes_usuario ON likes (usuario_id);

-- =============================================================================
-- FIN DEL SCRIPT
-- =============================================================================
