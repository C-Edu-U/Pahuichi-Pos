-- ---------------------------------------------------------------------------
-- La bitácora guarda también el nombre de la entidad como estaba en el momento
-- de la acción: si el turno se borra o se renombra después, la fila sigue
-- diciendo de qué turno se trataba.
-- ---------------------------------------------------------------------------
ALTER TABLE bitacora ADD COLUMN entidad_nombre VARCHAR(120) NULL AFTER entidad_id;

-- Los turnos que ya estaban en la bitácora toman el nombre que quedó en el
-- detalle de la fila que los agregó o los eliminó. Para eso se levanta un
-- momento la traba que impide editar la bitácora y se vuelve a poner.
DROP TRIGGER IF EXISTS tr_bitacora_sin_editar;

UPDATE bitacora b
JOIN (
    SELECT entidad_id, MAX(JSON_UNQUOTE(JSON_EXTRACT(detalle, '$.nombre'))) AS nombre
    FROM bitacora
    WHERE entidad = 'turnos' AND JSON_EXTRACT(detalle, '$.nombre') IS NOT NULL
    GROUP BY entidad_id
) e ON e.entidad_id = b.entidad_id
SET b.entidad_nombre = e.nombre
WHERE b.entidad = 'turnos' AND b.entidad_nombre IS NULL;

CREATE TRIGGER tr_bitacora_sin_editar BEFORE UPDATE ON bitacora FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se edita';
