-- HU-44: anulación trazable; se conservan ventas, pagos, factura y correlativo.
CREATE TABLE IF NOT EXISTS venta_anulaciones (
  venta_id BIGINT UNSIGNED NOT NULL,
  factura_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  motivo VARCHAR(120) NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (venta_id),
  UNIQUE KEY uq_anulacion_factura (factura_id),
  CONSTRAINT fk_anulacion_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
  CONSTRAINT fk_anulacion_factura FOREIGN KEY (factura_id) REFERENCES facturas(id),
  CONSTRAINT fk_anulacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
