-- Pahuichi POS · arqueo automático y resumen de cierre (HU-24)
-- La tabla ventas es la base que usarán las historias del punto de venta; el
-- arqueo necesita leerla para saber cuánto efectivo entró por la venta.

CREATE TABLE ventas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_id INT UNSIGNED NOT NULL,
  turno_id SMALLINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  metodo_pago ENUM('efectivo', 'qr', 'tarjeta', 'otro') NOT NULL DEFAULT 'efectivo',
  estado_pago ENUM('pagado', 'pendiente', 'rechazado') NOT NULL DEFAULT 'pagado',
  monto_recibido DECIMAL(10,2) NULL,
  cambio DECIMAL(10,2) NULL,
  referencia_pago VARCHAR(64) NULL,
  descuento_motivo VARCHAR(200) NULL,
  anulada TINYINT(1) NOT NULL DEFAULT 0,
  motivo_anulacion VARCHAR(200) NULL,
  anulada_por INT UNSIGNED NULL,
  anulada_en DATETIME NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ventas_caja (caja_id),
  KEY ix_ventas_fecha (fecha),
  KEY ix_ventas_metodo (caja_id, metodo_pago, estado_pago),
  CONSTRAINT fk_ventas_caja FOREIGN KEY (caja_id) REFERENCES cajas (id),
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
  CONSTRAINT ck_ventas_total CHECK (total >= 0),
  CONSTRAINT ck_ventas_descuento CHECK (descuento >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El arqueo guarda el efectivo esperado, la diferencia y el resumen del turno.
ALTER TABLE arqueos
  ADD COLUMN efectivo_esperado DECIMAL(10,2) NULL AFTER total_contado,
  ADD COLUMN diferencia DECIMAL(10,2) NULL AFTER efectivo_esperado,
  ADD COLUMN observacion VARCHAR(255) NULL AFTER diferencia,
  ADD COLUMN resumen JSON NULL AFTER observacion,
  ADD COLUMN cerrado_en DATETIME NULL AFTER resumen;

-- La caja guarda cuándo se cerró.
ALTER TABLE cajas
  ADD COLUMN cerrada_en DATETIME NULL AFTER abierta_en;
