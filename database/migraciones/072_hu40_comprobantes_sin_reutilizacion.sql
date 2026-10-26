-- Consolida solo el indice auxiliar de comprobantes. Conserva ventas, pagos y fotos.
-- Si la version diaria ya contenia referencias repetidas, mantiene la primera fecha.
DELETE posterior FROM qr_comprobantes posterior
 JOIN qr_comprobantes primero ON primero.comprobante=posterior.comprobante
 AND primero.fecha<posterior.fecha;
-- Una referencia registrada no se vuelve a reservar al cambiar de dia.
ALTER TABLE qr_comprobantes DROP PRIMARY KEY, ADD PRIMARY KEY (comprobante);
