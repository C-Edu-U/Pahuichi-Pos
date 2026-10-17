-- HU-10: soporte mínimo de venta en efectivo y factura persistente.
-- 002 queda reservada para caja (Josue); no se modifica el esquema de cajas.
CREATE TABLE ventas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  solicitud_hash CHAR(64) NOT NULL,
  caja_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo_pedido ENUM('mesa', 'llevar') NOT NULL,
  mesa VARCHAR(2) NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  estado ENUM('cobrada', 'anulada') NOT NULL DEFAULT 'cobrada',
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ventas_uuid (uuid),
  KEY ix_ventas_caja (caja_id, creado_en),
  CONSTRAINT fk_ventas_caja FOREIGN KEY (caja_id) REFERENCES cajas(id),
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT ck_ventas_total CHECK (subtotal > 0 AND descuento >= 0 AND total = subtotal - descuento),
  CONSTRAINT ck_ventas_mesa CHECK ((tipo_pedido = 'mesa' AND mesa IS NOT NULL) OR (tipo_pedido = 'llevar' AND mesa IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_venta (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id BIGINT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL,
  nota VARCHAR(60) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT ck_detalle_montos CHECK (cantidad > 0 AND precio_unitario > 0 AND descuento >= 0 AND subtotal = cantidad * precio_unitario - descuento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id BIGINT UNSIGNED NOT NULL,
  metodo ENUM('efectivo') NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  recibido DECIMAL(10,2) NOT NULL,
  cambio DECIMAL(10,2) NOT NULL,
  estado ENUM('confirmado') NOT NULL DEFAULT 'confirmado',
  PRIMARY KEY (id),
  CONSTRAINT fk_pago_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT ck_pago_montos CHECK (monto > 0 AND recibido >= monto AND cambio = recibido - monto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE correlativos (
  punto_venta SMALLINT UNSIGNED NOT NULL,
  ultimo_numero BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (punto_venta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facturas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id BIGINT UNSIGNED NOT NULL,
  punto_venta SMALLINT UNSIGNED NOT NULL,
  numero BIGINT UNSIGNED NOT NULL,
  cuf CHAR(28) NOT NULL,
  cufd VARCHAR(120) NOT NULL,
  fecha_emision DATETIME NOT NULL,
  leyenda_id SMALLINT UNSIGNED NOT NULL,
  leyenda VARCHAR(255) NOT NULL,
  monto_total DECIMAL(10,2) NOT NULL,
  importe_base_cf DECIMAL(10,2) NOT NULL,
  estado ENUM('emitida', 'anulada') NOT NULL DEFAULT 'emitida',
  snapshot_json LONGTEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_factura_venta (venta_id),
  UNIQUE KEY uq_factura_numero (punto_venta, numero),
  UNIQUE KEY uq_factura_cuf (cuf),
  CONSTRAINT fk_factura_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT fk_factura_leyenda FOREIGN KEY (leyenda_id) REFERENCES leyendas(id),
  CONSTRAINT ck_factura_monto CHECK (monto_total > 0 AND importe_base_cf = monto_total),
  CONSTRAINT ck_factura_snapshot CHECK (JSON_VALID(snapshot_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
