-- Pahuichi POS · restricciones combinables de las promociones (HU-49, RF-51)
-- Va después de 060 porque altera su tabla. Todas son opcionales y se combinan:
-- la promoción se aplica solo si cumple todas las que tenga.
--   fecha_desde / fecha_hasta  rango de fechas, ambos incluidos
--   dias                       días de la semana como bits: lunes = 1, martes = 2, … domingo = 64 (127 = todos)
--   hora_desde / hora_hasta    franja horaria; el fin no se incluye y 24:00:00 es medianoche (como los turnos)
--   cantidad_minima            unidades del producto o de la categoría en el pedido
--   monto_minimo               subtotal del pedido, antes de descuentos
--   tope_usos_dia              ventas por día en que puede aplicarse

ALTER TABLE promociones
  ADD COLUMN fecha_desde DATE NULL AFTER activo,
  ADD COLUMN fecha_hasta DATE NULL AFTER fecha_desde,
  ADD COLUMN dias TINYINT UNSIGNED NOT NULL DEFAULT 127 AFTER fecha_hasta,
  ADD COLUMN hora_desde TIME NULL AFTER dias,
  ADD COLUMN hora_hasta TIME NULL AFTER hora_desde,
  ADD COLUMN cantidad_minima SMALLINT UNSIGNED NULL AFTER hora_hasta,
  ADD COLUMN monto_minimo DECIMAL(10,2) NULL AFTER cantidad_minima,
  ADD COLUMN tope_usos_dia SMALLINT UNSIGNED NULL AFTER monto_minimo,
  ADD CONSTRAINT ck_promociones_fechas CHECK (fecha_desde IS NULL OR fecha_hasta IS NULL OR fecha_hasta >= fecha_desde),
  ADD CONSTRAINT ck_promociones_dias CHECK (dias BETWEEN 1 AND 127),
  ADD CONSTRAINT ck_promociones_horas CHECK ((hora_desde IS NULL AND hora_hasta IS NULL)
    OR (hora_desde IS NOT NULL AND hora_hasta IS NOT NULL AND hora_hasta > hora_desde AND hora_hasta <= '24:00:00')),
  ADD CONSTRAINT ck_promociones_condiciones CHECK ((cantidad_minima IS NULL OR cantidad_minima >= 2)
    AND (monto_minimo IS NULL OR monto_minimo > 0) AND (tope_usos_dia IS NULL OR tope_usos_dia >= 1));
