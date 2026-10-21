-- Pahuichi POS · estados de pago que distingue el arqueo (HU-24, RF-22)
-- pagos lo crea 057_hu57_correlativos.sql solo para efectivo confirmado. El resumen
-- de cierre separa los QR confirmados, pendientes y rechazados (estos dos no suman
-- al efectivo esperado), así que el esquema ya admite esos valores. El cobro QR
-- (HU-39) y la conciliación (HU-41) los usarán.

ALTER TABLE pagos
  MODIFY metodo ENUM('efectivo', 'qr') NOT NULL,
  MODIFY estado ENUM('confirmado', 'pendiente', 'rechazado') NOT NULL DEFAULT 'confirmado';
