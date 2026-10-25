-- Pahuichi POS · la caja guarda el horario de su turno (HU-11)
-- Al abrir, la caja copia el horario que el turno tiene en ese momento. Así, si el
-- administrador cambia el horario del turno, las cajas ya abiertas o cerradas y sus
-- reportes siguen mostrando el horario con el que trabajaron.
-- Las cajas que ya existían se completan con el horario actual de su turno.

ALTER TABLE cajas
  ADD COLUMN turno_inicio TIME NULL AFTER turno_id,
  ADD COLUMN turno_fin TIME NULL AFTER turno_inicio;

UPDATE cajas c JOIN turnos t ON t.id = c.turno_id
SET c.turno_inicio = t.hora_inicio, c.turno_fin = t.hora_fin
WHERE c.turno_inicio IS NULL;
