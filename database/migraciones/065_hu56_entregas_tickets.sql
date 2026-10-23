-- HU-56: distingue la entrega inicial de la recuperación posterior. No cambia ventas ni facturas.
CREATE TABLE IF NOT EXISTS entregas_tickets (
    factura_id BIGINT UNSIGNED NOT NULL,
    entregado_comanda TINYINT UNSIGNED NOT NULL DEFAULT 0,
    entregado_copia TINYINT UNSIGNED NOT NULL DEFAULT 0,
    entregado_factura TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cerrada TINYINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (factura_id),
    CONSTRAINT fk_entrega_ticket_factura FOREIGN KEY (factura_id) REFERENCES facturas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las facturas existentes son históricas: no pueden solicitar nuevos originales.
INSERT IGNORE INTO entregas_tickets (factura_id, entregado_comanda, entregado_copia, entregado_factura, cerrada)
SELECT id, 1, 1, 1, 1 FROM facturas;
