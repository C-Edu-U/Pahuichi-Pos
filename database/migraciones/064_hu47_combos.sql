-- Pahuichi POS · combos a precio especial (HU-47, RF-49)
-- «Majadito + mocochinchi a Bs 45»: los productos del combo van en promocion_items y el
-- precio en precio_combo. El combo no usa producto ni categoría: sus productos son los
-- items. Va después de 063 porque altera la tabla de 060 y sus restricciones de 060 y 062.

ALTER TABLE promociones
  MODIFY tipo ENUM('descuento', 'regalo', 'combo') NOT NULL DEFAULT 'descuento',
  ADD COLUMN precio_combo DECIMAL(10,2) NULL AFTER regalo_producto_id,
  DROP CONSTRAINT ck_promociones_objetivo,
  DROP CONSTRAINT ck_promociones_tipo,
  ADD CONSTRAINT ck_promociones_objetivo CHECK (
    (tipo = 'combo' AND producto_id IS NULL AND categoria_id IS NULL)
    OR (tipo <> 'combo' AND (producto_id IS NULL) <> (categoria_id IS NULL))),
  ADD CONSTRAINT ck_promociones_tipo CHECK (
    (tipo = 'descuento' AND valor_tipo IS NOT NULL AND valor IS NOT NULL AND valor > 0 AND (valor_tipo = 'monto' OR valor < 100)
      AND cantidad_compra IS NULL AND regalo_producto_id IS NULL AND precio_combo IS NULL)
    OR (tipo = 'regalo' AND valor_tipo IS NULL AND valor IS NULL AND cantidad_compra BETWEEN 1 AND 99 AND regalo_producto_id IS NOT NULL
      AND precio_combo IS NULL)
    OR (tipo = 'combo' AND valor_tipo IS NULL AND valor IS NULL AND cantidad_compra IS NULL AND regalo_producto_id IS NULL
      AND precio_combo IS NOT NULL AND precio_combo > 0));

-- Productos de cada combo y cuántas unidades lleva de cada uno
CREATE TABLE IF NOT EXISTS promocion_items (
  promocion_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (promocion_id, producto_id),
  CONSTRAINT fk_promocion_items_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id),
  CONSTRAINT fk_promocion_items_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT ck_promocion_items_cantidad CHECK (cantidad BETWEEN 1 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
