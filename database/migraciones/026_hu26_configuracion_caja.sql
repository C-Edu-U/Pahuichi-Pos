-- Pahuichi POS · configuración editable del cierre de caja (HU-26)
-- El umbral de la observación obligatoria vive en la base para que el
-- administrador lo cambie sin tocar el código.

CREATE TABLE configuracion (
  clave VARCHAR(60) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diferencia igual o mayor a Bs 10 obliga a escribir una observación al cerrar.
INSERT INTO configuracion (clave, valor) VALUES ('umbral_observacion_caja', '10.00');
