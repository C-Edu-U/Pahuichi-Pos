-- La clave diaria protege incluso cobros simultaneos desde cajas distintas.
CREATE TABLE IF NOT EXISTS qr_comprobantes (
 fecha DATE NOT NULL,
 comprobante VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 pago_id BIGINT UNSIGNED NULL,
 PRIMARY KEY (fecha, comprobante),
 CONSTRAINT fk_qrc_pago FOREIGN KEY (pago_id) REFERENCES pagos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Conserva todos los pagos anteriores. Si ya habia duplicados, referencia el primero.
INSERT INTO qr_comprobantes (fecha, comprobante, pago_id)
 SELECT DATE(v.creado_en), qp.comprobante COLLATE utf8mb4_bin, MIN(qp.pago_id)
 FROM qr_pagos qp JOIN pagos p ON p.id=qp.pago_id JOIN ventas v ON v.id=p.venta_id
 GROUP BY DATE(v.creado_en), qp.comprobante COLLATE utf8mb4_bin
 ON DUPLICATE KEY UPDATE pago_id=COALESCE(qr_comprobantes.pago_id, VALUES(pago_id));
