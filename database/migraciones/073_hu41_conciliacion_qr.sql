CREATE TABLE IF NOT EXISTS qr_conciliaciones (
 pago_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 usuario_id INT UNSIGNED NOT NULL,
 estado ENUM('confirmado','rechazado') NOT NULL,
 resuelto_en DATETIME NOT NULL,
 CONSTRAINT fk_qrconc_pago FOREIGN KEY (pago_id) REFERENCES pagos(id),
 CONSTRAINT fk_qrconc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS qr_avisos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pago_id BIGINT UNSIGNED NOT NULL,
 usuario_id INT UNSIGNED NOT NULL,
 creado_en DATETIME NOT NULL,
 visto_en DATETIME NULL,
 UNIQUE KEY uq_qraviso_pago_usuario (pago_id,usuario_id),
 KEY ix_qraviso_usuario (usuario_id,visto_en),
 CONSTRAINT fk_qraviso_pago FOREIGN KEY (pago_id) REFERENCES pagos(id),
 CONSTRAINT fk_qraviso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
