-- Pahuichi POS · descuento manual autorizado con el PIN del administrador (HU-06, RF-05)
-- ventas ya guarda el monto en «descuento» (057_hu57_correlativos.sql); aquí se agrega
-- el motivo y quién lo autorizó. Va después de 057 porque altera su tabla.

ALTER TABLE ventas
  ADD COLUMN descuento_motivo VARCHAR(120) NULL AFTER descuento,
  ADD COLUMN descuento_autorizado_por INT UNSIGNED NULL AFTER descuento_motivo,
  ADD CONSTRAINT fk_ventas_descuento_autoriza FOREIGN KEY (descuento_autorizado_por) REFERENCES usuarios(id),
  ADD CONSTRAINT ck_ventas_descuento CHECK (descuento >= 0 AND descuento < subtotal AND total = subtotal - descuento);
