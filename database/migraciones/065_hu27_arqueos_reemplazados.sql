-- Pahuichi POS · reabrir una caja sin perder su arqueo (HU-27)
-- Al reabrir, el arqueo vigente no se borra: queda reemplazado, con el motivo y
-- quién reabrió. Una caja tiene a lo sumo un arqueo vigente (caja_vigente vale
-- caja_id solo mientras no fue reemplazado) y puede tener varios reemplazados.

ALTER TABLE arqueos
  ADD COLUMN reemplazado_en DATETIME NULL AFTER cerrado_en,
  ADD COLUMN reabierta_por INT UNSIGNED NULL AFTER reemplazado_en,
  ADD COLUMN motivo_reapertura VARCHAR(255) NULL AFTER reabierta_por,
  ADD COLUMN caja_vigente INT UNSIGNED AS (IF(reemplazado_en IS NULL, caja_id, NULL)) STORED,
  ADD KEY idx_arqueos_caja (caja_id),
  ADD CONSTRAINT fk_arqueos_reabierta_por FOREIGN KEY (reabierta_por) REFERENCES usuarios (id);

ALTER TABLE arqueos
  DROP INDEX uq_arqueos_caja,
  ADD UNIQUE KEY uq_arqueos_vigente (caja_vigente);
