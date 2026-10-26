CREATE TABLE IF NOT EXISTS qr_pagos (
 pago_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 qr_diario_id BIGINT UNSIGNED NOT NULL,
 comprobante VARCHAR(80) NOT NULL,
 pagador VARCHAR(120) NULL,
 foto MEDIUMBLOB NULL,
 foto_tipo VARCHAR(30) NULL,
 CONSTRAINT fk_qrp_pago FOREIGN KEY (pago_id) REFERENCES pagos(id),
 CONSTRAINT fk_qrp_diario FOREIGN KEY (qr_diario_id) REFERENCES qr_diarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
