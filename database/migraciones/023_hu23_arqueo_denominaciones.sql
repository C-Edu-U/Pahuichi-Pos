-- Pahuichi POS · arqueo de caja (HU-23)
-- El conteo por denominación se guarda aparte del efectivo esperado: el cajero
-- cuenta a ciegas y el esperado se calcula después (HU-24).

CREATE TABLE arqueos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_id INT UNSIGNED NOT NULL,
  denominaciones JSON NOT NULL,
  total_contado DECIMAL(10,2) NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_arqueos_caja (caja_id),
  CONSTRAINT fk_arqueos_caja FOREIGN KEY (caja_id) REFERENCES cajas (id),
  CONSTRAINT ck_arqueos_total CHECK (total_contado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
