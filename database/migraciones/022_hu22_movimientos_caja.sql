-- Pahuichi POS · ingresos y egresos de efectivo de la caja (HU-22)
-- Un movimiento pertenece a una caja abierta y queda con su responsable y su hora.

CREATE TABLE movimientos_caja (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_id INT UNSIGNED NOT NULL,
  tipo ENUM('ingreso', 'egreso') NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  motivo VARCHAR(200) NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_movimientos_caja (caja_id, creado_en),
  CONSTRAINT fk_movimientos_caja FOREIGN KEY (caja_id) REFERENCES cajas (id),
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
  CONSTRAINT ck_movimientos_monto CHECK (monto > 0),
  CONSTRAINT ck_movimientos_motivo CHECK (CHAR_LENGTH(TRIM(motivo)) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
