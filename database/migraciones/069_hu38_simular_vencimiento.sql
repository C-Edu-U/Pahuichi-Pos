-- Demostracion HU-38: solo el QR seleccionado puede vencer antes de medianoche.
ALTER TABLE qr_diarios ADD COLUMN vencimiento_demo DATETIME NULL DEFAULT NULL;
