-- Pahuichi POS · arqueo automático y resumen de cierre (HU-24)
-- Las ventas y sus pagos los crea 057_hu57_correlativos.sql: el arqueo los lee
-- (efectivo confirmado de ventas no anuladas) y no crea tablas propias de venta.

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
