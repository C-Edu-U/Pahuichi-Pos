-- Pahuichi POS · cuentas por mesa (HU-35)
-- El mesero abre la cuenta de una mesa con su primer pedido y le suma pedidos (rondas).
-- Cada ronda llega a caja y genera su comanda para cocina; sus promociones se calculan a
-- la hora de la ronda. La factura sale una sola vez, al cerrar y cobrar la cuenta (lo
-- hace el mesero o caja), con todas las rondas; la venta queda en la caja abierta.
-- Va después de 057 (ventas) y conserva _hu35_ en el nombre (decisión 32).

CREATE TABLE IF NOT EXISTS cuentas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mesa VARCHAR(2) NOT NULL,
  abierta_por INT UNSIGNED NOT NULL,
  estado ENUM('abierta', 'cerrada', 'cancelada') NOT NULL DEFAULT 'abierta',
  abierta_en DATETIME NOT NULL,
  cerrada_en DATETIME NULL,
  cerrada_por INT UNSIGNED NULL,
  venta_id BIGINT UNSIGNED NULL,
  -- Una sola cuenta abierta por mesa: vale la mesa solo mientras está abierta
  mesa_abierta VARCHAR(2) AS (IF(estado = 'abierta', mesa, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cuentas_mesa_abierta (mesa_abierta),
  UNIQUE KEY uq_cuentas_venta (venta_id),
  KEY ix_cuentas_estado (estado, abierta_en),
  CONSTRAINT fk_cuentas_abierta_por FOREIGN KEY (abierta_por) REFERENCES usuarios (id),
  CONSTRAINT fk_cuentas_cerrada_por FOREIGN KEY (cerrada_por) REFERENCES usuarios (id),
  CONSTRAINT fk_cuentas_venta FOREIGN KEY (venta_id) REFERENCES ventas (id),
  CONSTRAINT ck_cuentas_mesa CHECK (mesa REGEXP '^[1-9][0-9]?$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuenta_rondas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id BIGINT UNSIGNED NOT NULL,
  numero SMALLINT UNSIGNED NOT NULL,
  uuid CHAR(36) NOT NULL,
  solicitud_hash CHAR(64) NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  estado ENUM('enviada', 'cancelada') NOT NULL DEFAULT 'enviada',
  bruto DECIMAL(10,2) NOT NULL,
  promociones DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL,
  -- Hora con la que se calcularon sus promociones (la del pedido)
  pedida_en DATETIME NOT NULL,
  creado_en DATETIME NOT NULL,
  -- Primera vez que caja abrió su comanda: las que no, se avisan como nuevas
  comanda_vista_en DATETIME NULL,
  cancelada_en DATETIME NULL,
  cancelada_por INT UNSIGNED NULL,
  motivo_cancelacion VARCHAR(120) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cuenta_rondas_uuid (uuid),
  UNIQUE KEY uq_cuenta_rondas_numero (cuenta_id, numero),
  CONSTRAINT fk_cuenta_rondas_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas (id),
  CONSTRAINT fk_cuenta_rondas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
  CONSTRAINT fk_cuenta_rondas_cancelada_por FOREIGN KEY (cancelada_por) REFERENCES usuarios (id),
  CONSTRAINT ck_cuenta_rondas_montos CHECK (bruto > 0 AND promociones >= 0 AND subtotal = bruto - promociones)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada línea guarda lo que se cobrará: precio, descuento y promociones de la hora de la ronda
CREATE TABLE IF NOT EXISTS ronda_lineas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ronda_id BIGINT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  datos JSON NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ronda_lineas_ronda (ronda_id),
  CONSTRAINT fk_ronda_lineas_ronda FOREIGN KEY (ronda_id) REFERENCES cuenta_rondas (id),
  CONSTRAINT fk_ronda_lineas_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT ck_ronda_lineas CHECK (cantidad > 0 AND subtotal >= 0 AND JSON_VALID(datos))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
