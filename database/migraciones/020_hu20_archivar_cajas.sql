-- Pahuichi POS · cajas archivadas (HU-20)
-- «Limpiar historial» ya no borra: oculta del historial las cajas cerradas.
-- Nada se borra (RNF-20): arqueos, movimientos, ventas y facturas se conservan,
-- y la regla de una caja por turno y día sigue valiendo.

ALTER TABLE cajas
  ADD COLUMN archivada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;
