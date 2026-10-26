-- Pahuichi POS · promoción de regalo por compra (escenario de HU-46; en el backlog, HU-48)
-- «Por cada N del producto o la categoría que se compra, se agrega 1 del producto regalo a Bs 0.»
-- Va después de 060 porque altera su tabla. El regalo no tiene valor: valor y valor_tipo
-- quedan solo para el tipo «descuento».

ALTER TABLE promociones
  MODIFY tipo ENUM('descuento', 'regalo') NOT NULL DEFAULT 'descuento',
  MODIFY valor_tipo ENUM('porcentaje', 'monto') NULL,
  MODIFY valor DECIMAL(10,2) NULL,
  ADD COLUMN cantidad_compra TINYINT UNSIGNED NULL AFTER categoria_id,
  ADD COLUMN regalo_producto_id INT UNSIGNED NULL AFTER cantidad_compra,
  ADD CONSTRAINT fk_promociones_regalo FOREIGN KEY (regalo_producto_id) REFERENCES productos (id),
  DROP CONSTRAINT ck_promociones_valor,
  ADD CONSTRAINT ck_promociones_tipo CHECK (
    (tipo = 'descuento' AND valor_tipo IS NOT NULL AND valor IS NOT NULL AND valor > 0 AND (valor_tipo = 'monto' OR valor < 100)
      AND cantidad_compra IS NULL AND regalo_producto_id IS NULL)
    OR (tipo = 'regalo' AND valor_tipo IS NULL AND valor IS NULL AND cantidad_compra BETWEEN 1 AND 99 AND regalo_producto_id IS NOT NULL));
