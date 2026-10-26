-- 0 representa un QR bancario de importe libre; los cobros guardan el total real.
-- Se conservan todos los QR y pagos anteriores y se siguen rechazando negativos.
ALTER TABLE qr_diarios DROP CONSTRAINT ck_qr_diario_monto,
 ADD CONSTRAINT ck_qr_diario_monto CHECK (monto >= 0);
