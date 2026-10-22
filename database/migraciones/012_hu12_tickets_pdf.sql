-- HU-12: perfiles de salida PDF. No modifica ventas ni facturas emitidas.
CREATE TABLE IF NOT EXISTS configuracion_tickets (
    destino VARCHAR(10) NOT NULL PRIMARY KEY,
    ancho SMALLINT UNSIGNED NOT NULL DEFAULT 76,
    columnas TINYINT UNSIGNED NOT NULL DEFAULT 42,
    copias TINYINT UNSIGNED NOT NULL DEFAULT 1,
    factura TINYINT(1) NOT NULL DEFAULT 0,
    copia TINYINT(1) NOT NULL DEFAULT 0,
    comanda TINYINT(1) NOT NULL DEFAULT 0,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_ticket_destino CHECK (destino IN ('caja', 'cocina')),
    CONSTRAINT chk_ticket_ancho CHECK (ancho IN (58, 76)),
    CONSTRAINT chk_ticket_columnas CHECK (columnas BETWEEN 24 AND 48),
    CONSTRAINT chk_ticket_copias CHECK (copias BETWEEN 1 AND 3),
    CONSTRAINT chk_ticket_seleccion CHECK (factura + copia + comanda >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO configuracion_tickets (destino, ancho, columnas, copias, factura, copia, comanda) VALUES
    ('caja', 76, 42, 1, 1, 1, 0),
    ('cocina', 76, 42, 1, 0, 0, 1);
