-- Pahuichi POS · promociones acumulables y no acumulables (HU-50, RF-53, RN-09)
-- Va después de 062 porque altera la tabla de 060. Una promoción no acumulable no se
-- combina con ninguna otra del pedido: compite y gana la de mayor beneficio. Las
-- acumulables se aplican juntas por prioridad (1 primero). Las existentes quedan como
-- no acumulables: el administrador decide cuáles se suman.

ALTER TABLE promociones
  ADD COLUMN acumulable TINYINT(1) NOT NULL DEFAULT 0 AFTER tope_usos_dia,
  ADD COLUMN prioridad TINYINT UNSIGNED NOT NULL DEFAULT 10 AFTER acumulable,
  ADD CONSTRAINT ck_promociones_prioridad CHECK (prioridad BETWEEN 1 AND 99);
