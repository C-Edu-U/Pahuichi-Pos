-- Pahuichi POS · motor de promociones: descuento por producto o categoría (HU-46, RF-31, RF-48, RN-08)
-- Va después de 059 porque registra el uso en ventas y detalle_venta (057).
-- Este archivo solo trae el tipo «descuento». Combos, regalos, cumpleaños, restricciones
-- de horario y la regla de acumulables (HU-47 a HU-51) agregan sus columnas con su historia.

CREATE TABLE IF NOT EXISTS promociones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(40) NOT NULL,
  tipo ENUM('descuento') NOT NULL DEFAULT 'descuento',
  valor_tipo ENUM('porcentaje', 'monto') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  producto_id INT UNSIGNED NULL,
  categoria_id SMALLINT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_promociones_activo (activo),
  CONSTRAINT fk_promociones_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_promociones_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT ck_promociones_objetivo CHECK ((producto_id IS NULL) <> (categoria_id IS NULL)),
  CONSTRAINT ck_promociones_valor CHECK (valor > 0 AND (valor_tipo = 'monto' OR valor < 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué promoción descontó cada línea vendida. La etiqueta se copia al vender: si la
-- promoción cambia de nombre después, la venta conserva el que tenía.
CREATE TABLE IF NOT EXISTS venta_promociones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id BIGINT UNSIGNED NOT NULL,
  detalle_id BIGINT UNSIGNED NOT NULL,
  promocion_id INT UNSIGNED NOT NULL,
  etiqueta VARCHAR(40) NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_venta_promociones_linea (detalle_id, promocion_id),
  KEY ix_venta_promociones_promocion (promocion_id, venta_id),
  CONSTRAINT fk_venta_promociones_venta FOREIGN KEY (venta_id) REFERENCES ventas (id),
  CONSTRAINT fk_venta_promociones_detalle FOREIGN KEY (detalle_id) REFERENCES detalle_venta (id),
  CONSTRAINT fk_venta_promociones_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id),
  CONSTRAINT ck_venta_promociones_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
