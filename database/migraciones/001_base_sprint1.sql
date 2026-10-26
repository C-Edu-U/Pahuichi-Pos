-- Pahuichi POS · esquema base del Sprint 1
-- Usuarios y acceso, bitácora, configuración del negocio, turnos, catálogo y cajas.
-- Los sprints siguientes agregan sus tablas en migraciones nuevas; esta no se edita.

-- ---------------------------------------------------------------------------
-- Usuarios y acceso (HU-02, HU-03)
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  usuario VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NULL,
  pin_hash VARCHAR(255) NULL,
  rol ENUM('administrador', 'cajero', 'mesero', 'cocina') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  debe_cambiar_clave TINYINT(1) NOT NULL DEFAULT 0,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  ultimo_ingreso DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario),
  KEY ix_usuarios_rol_activo (rol, activo),
  CONSTRAINT ck_usuarios_usuario CHECK (usuario REGEXP '^[a-z0-9]{3,20}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de PIN por equipo: el PIN no dice quién es el usuario,
-- así que el bloqueo de 15 minutos se lleva por dirección IP.
CREATE TABLE intentos_acceso (
  clave VARCHAR(64) NOT NULL,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Bitácora: solo inserción (RNF-20). Los disparadores impiden editar o borrar.
-- ---------------------------------------------------------------------------
CREATE TABLE bitacora (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  accion VARCHAR(40) NOT NULL,
  entidad VARCHAR(40) NULL,
  entidad_id VARCHAR(40) NULL,
  detalle JSON NULL,
  ip VARCHAR(45) NULL,
  creado_en DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY ix_bitacora_fecha (creado_en),
  KEY ix_bitacora_usuario (usuario_id, creado_en),
  KEY ix_bitacora_accion (accion, creado_en),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER tr_bitacora_sin_editar BEFORE UPDATE ON bitacora FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se edita';

CREATE TRIGGER tr_bitacora_sin_borrar BEFORE DELETE ON bitacora FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se borra';

-- ---------------------------------------------------------------------------
-- Configuración (HU-08, HU-09, HU-10, HU-11). negocio y parametros_fiscales tienen una sola fila.
-- ---------------------------------------------------------------------------
CREATE TABLE negocio (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  razon_social VARCHAR(120) NOT NULL,
  nombre_comercial VARCHAR(80) NOT NULL,
  nit VARCHAR(13) NOT NULL,
  direccion VARCHAR(160) NOT NULL,
  telefono VARCHAR(20) NULL,
  municipio VARCHAR(60) NOT NULL,
  logo VARCHAR(80) NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT ck_negocio_fila_unica CHECK (id = 1),
  CONSTRAINT ck_negocio_nit CHECK (nit REGEXP '^[0-9]{7,13}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parametros_fiscales (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  codigo_sucursal SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  codigo_punto_venta SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  actividad_economica VARCHAR(120) NOT NULL,
  cuis VARCHAR(20) NOT NULL,
  cufd VARCHAR(120) NOT NULL,
  cufd_codigo_control VARCHAR(40) NOT NULL,
  cufd_vigencia DATETIME NOT NULL,
  numero_inicial INT UNSIGNED NOT NULL DEFAULT 1,
  modalidad ENUM('computarizada', 'electronica') NOT NULL DEFAULT 'computarizada',
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT ck_fiscal_fila_unica CHECK (id = 1),
  CONSTRAINT ck_fiscal_numero_inicial CHECK (numero_inicial >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leyendas (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  texto VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leyendas_texto (texto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hora_fin admite 24:00:00 para «hasta medianoche»
CREATE TABLE turnos (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(20) NOT NULL,
  nombre VARCHAR(30) NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  orden TINYINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turnos_clave (clave),
  UNIQUE KEY uq_turnos_nombre (nombre),
  CONSTRAINT ck_turnos_horario CHECK (hora_inicio < hora_fin AND hora_fin <= '24:00:00')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catálogo (HU-13, HU-14)
-- ---------------------------------------------------------------------------
CREATE TABLE categorias (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(24) NOT NULL,
  orden TINYINT UNSIGNED NOT NULL,
  color CHAR(7) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre),
  KEY ix_categorias_orden (activo, orden),
  CONSTRAINT ck_categorias_color CHECK (color REGEXP '^#[0-9a-f]{6}$'),
  CONSTRAINT ck_categorias_orden CHECK (orden BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(12) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  nombre_corto VARCHAR(16) NOT NULL,
  descripcion VARCHAR(200) NULL,
  categoria_id SMALLINT UNSIGNED NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  unidad ENUM('plato', 'porcion', 'vaso', 'jarra', 'botella', 'copa', 'unidad') NOT NULL,
  foto VARCHAR(80) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  agotado TINYINT(1) NOT NULL DEFAULT 0,
  favorito TINYINT(1) NOT NULL DEFAULT 0,
  va_a_cocina TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_codigo (codigo),
  KEY ix_productos_categoria (categoria_id, activo),
  KEY ix_productos_favorito (favorito, activo),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT ck_productos_codigo CHECK (codigo REGEXP '^[A-Z0-9-]{2,12}$'),
  CONSTRAINT ck_productos_precio CHECK (precio > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Cajas (HU-20, HU-21). El cierre y el arqueo agregan sus columnas en el Sprint 2.
-- Una sola caja abierta (RN-01): abierta_unica vale 1 solo mientras está abierta,
-- y su índice único no deja que haya dos. Una caja por turno y fecha.
-- ---------------------------------------------------------------------------
CREATE TABLE cajas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  turno_id TINYINT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  cajero_id INT UNSIGNED NOT NULL,
  abierta_por INT UNSIGNED NOT NULL,
  fondo_inicial DECIMAL(10,2) NOT NULL,
  abierta_en DATETIME NOT NULL,
  estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
  abierta_unica TINYINT AS (IF(estado = 'abierta', 1, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cajas_una_abierta (abierta_unica),
  UNIQUE KEY uq_cajas_turno_fecha (fecha, turno_id),
  KEY ix_cajas_cajero (cajero_id, fecha),
  CONSTRAINT fk_cajas_turno FOREIGN KEY (turno_id) REFERENCES turnos (id),
  CONSTRAINT fk_cajas_cajero FOREIGN KEY (cajero_id) REFERENCES usuarios (id),
  CONSTRAINT fk_cajas_abierta_por FOREIGN KEY (abierta_por) REFERENCES usuarios (id),
  CONSTRAINT ck_cajas_fondo CHECK (fondo_inicial >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
