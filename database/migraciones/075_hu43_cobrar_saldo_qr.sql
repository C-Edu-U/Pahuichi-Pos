-- HU-43: relaciona el QR rechazado con su cobro posterior; no genera otra venta/factura.
CREATE TABLE IF NOT EXISTS qr_recuperaciones (
  pago_rechazado_id BIGINT UNSIGNED NOT NULL,
  pago_cobro_id BIGINT UNSIGNED NOT NULL,
  factura_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (pago_rechazado_id),
  UNIQUE KEY uq_qr_recuperacion_cobro (pago_cobro_id),
  KEY ix_qr_recuperacion_factura (factura_id),
  CONSTRAINT fk_qr_recuperacion_rechazado FOREIGN KEY (pago_rechazado_id) REFERENCES pagos(id),
  CONSTRAINT fk_qr_recuperacion_cobro FOREIGN KEY (pago_cobro_id) REFERENCES pagos(id),
  CONSTRAINT fk_qr_recuperacion_factura FOREIGN KEY (factura_id) REFERENCES facturas(id),
  CONSTRAINT fk_qr_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
