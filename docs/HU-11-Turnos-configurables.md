# HU-11 · Turnos configurables

## Alcance de la entrega

Esta entrega deja la historia HU-11 completa y funcionando sobre `feature/HU-11-turnos`: el administrador configura el horario de los turnos desde Configuración > Turnos, el cajero solo puede abrir caja con los turnos configurados y la bitácora deja el tipo de actividad con el nombre del turno.

La rama no toca `origin/develop` ni ninguna rama de otrointegrante.

## Archivos que intervienen

| Archivo | Responsabilidad |
| --- | --- |
| `app/Controllers/ConfigController.php` | `turnos()` decide la acción según el botón enviado (`agregar`, `eliminar` o guardar) y delega en `agregarTurno()`, `eliminarTurno()` y `editarTurnos()`, que son ramas excluyentes: un error al agregar no borra los horarios ya escritos. `claveDe()` arma la clave interna del turno con el nombre. |
| `app/Models/Turno.php` | Consulta, alta, borrado, nombre y horario de los turnos. `tieneCajasHoy()` usa el reloj de la aplicación (`Reloj`), no la hora del sistema. `tieneCajas()` impide borrar un turno con cajas de otro día (la llave foránea lo rechaza). `cambiarNombre()` cambia el nombre sin tocar la clave interna. `hora()` da la hora que ve el navegador (la medianoche se ve 23:59) y `horario()` arma el «11:00 a 16:00» que se muestra en la ficha y al abrir caja. |
| `app/Views/config/index.php` | Sección de turnos: ficha de cada turno (nombre que se escribe, horario, orden, desde y hasta), resumen del día, aviso de errores y modal de alta (nombre, desde, hasta y orden del sistema). |
| `public/assets/js/config.js` | Revisión en vivo de los horarios y de los nombres (error bajo el campo y aviso flotante), horario y resumen que se rehacen solos, guarda bloqueada si el horario no cierra o el nombre está vacío o repetido, y borrado con confirmación o con el aviso del motivo. |
| `public/assets/css/modulos.css` | Ficha del turno (cabecera con el nombre que se escribe, el horario, el orden y pie con aviso y papelera). |
| `app/Models/Bitacora.php` | Junta el turno afectado para que la bitácora muestre su nombre y guarda ese nombre en la fila (`entidad_nombre`), para que siga ahí aunque el turno se borre. |
| `app/Services/BitacoraTexto.php` | La entidad «turnos» se lee como «Turno: Medio día» en vez de «Turnos», con el nombre que quedó guardado en la fila. |
| `config/bitacora.php` | Tipos de actividad `config.turnos`, `config.turnos.nombre`, `config.turnos.agregar` y `config.turnos.eliminar`, con las etiquetas de los campos `clave`, `horario`, `nombre`, `inicio` y `fin`. |
| `app/Controllers/CajaController.php` | Entrega `Turno::activos()`: en la pantalla de abrir caja solo aparecen los turnos configurados. |
| `app/Services/CajaService.php` | Rechaza con «Elige un turno de la lista.» un turno que no exista o no esté activo. |
| `database/seeds.sql` | Turnos de fábrica: Medio día (11:00 a 16:00) y Noche (18:00 a 23:59, la medianoche). |
| `database/migraciones/001_base_sprint1.sql` | Tabla `turnos`; `hora_fin` admite `24:00:00` para «hasta medianoche». |
| `database/migraciones/002_bitacora_entidad_nombre.sql` | Columna `entidad_nombre` en la bitácora: guarda el nombre del turno en el momento de la acción y completa el de las filas que ya estaban. |
| `tests/Integracion/TurnosTest.php` | 40 pruebas: los tres escenarios de aceptación y las reglas de agregar, eliminar, renombrar, cruzar turnos y las dos que quedan sin bloqueo (último turno y turno con cajas), más lo que la pantalla muestra (resumen del día, orden no editable, horario como en la caja, aviso del borrado imposible) y lo que la bitácora registra. |

## Reglas del negocio

- El turno siempre termina después de empezar. La base lo exige con `CHECK (hora_inicio < hora_fin AND hora_fin <= '24:00:00')`, y un turno no puede cruzar la medianoche: la noche de fábrica termina a las 24:00 en la base, pero en todas las pantallas se ve 23:59, tal como lo marca el administrador.
- Los turnos no se cruzan: cada uno empieza cuando el anterior ya terminó.
- Ningún turno está bloqueado: el horario y el nombre se pueden cambiar y el turno se puede eliminar a cualquier hora.
- No se puede eliminar el único turno que queda, ni un turno con cajas (hoy o de otro día).
- El nombre de un turno es único: dos turnos no pueden llamarse igual. La clave interna la arma el sistema con el nombre («Mañana» → `manana`); si esa clave ya existe, le pone un número (`manana2`). Si el nombre cambia, la clave no cambia: es solo interna.

## Cómo se usa la pantalla

1. **Cambiar el horario**: se edita «Desde» y «Hasta» de la ficha del turno y se pulsa **Guardar cambios**. La ficha escribe el horario como lo ve el cajero al abrir caja («Noche · 18:00 a 23:59»), con la misma hora que está marcada en los campos, y mientras se escribe ese horario y el resumen del día se rehacen solos.
2. **Cambiar el nombre**: el nombre de la ficha es un campo de texto: se escribe arriba y se pulsa **Guardar cambios**. El nombre no puede quedar vacío ni repetirse, y la papelera usa el nombre que está escrito en ese momento.
3. **Agregar un turno**: botón **Agregar turno** → se abre un modal con el nombre, el horario y el orden. El nombre se escribe a mano y el orden se ve pero no se edita: lo pone el sistema. La clave la pone el sistema también.
4. **Eliminar un turno**: el botón de papelera de la ficha → modal de confirmación. No hay que marcar nada ni usar un botón aparte.

## Avisos: cuándo no se puede

La pantalla explica en voz alta lo que no se puede hacer, en vez de dejar el botón sin efecto:

| Situación | Qué se ve |
| --- | --- |
| «Hasta» no es después de «Desde» | Error bajo el campo «Hasta», el campo queda marcado y sale un aviso flotante al salir del campo o al guardar. El guardado no se envía. |
| Un turno se cruza con otro | Error bajo el campo «Desde» del turno que se cruza, con el nombre y la hora de fin del otro. |
| Se quiere borrar el único turno | Aviso flotante «No se puede eliminar el único turno que queda.» y el motivo bajo la ficha. |
| El turno tiene cajas (hoy u otro día) | El servidor lo rechaza y la pantalla vuelve con el motivo en un recuadro arriba y en un aviso flotante. |
| El nombre queda vacío o se repite | Error bajo el nombre de la ficha que falla («Escribe el nombre del turno.» o «Ese nombre de turno ya existe.») y el guardado no se envía. |

## Escenario 1: turnos iniciales

Dado el sistema recién instalado, cuando el administrador revisa Configuración > Turnos, entonces existen Medio día (11:00 a 16:00) y Noche (18:00 a 23:59). La base guarda la medianoche como 24:00:00, pero ninguna pantalla la muestra así: se ve 23:59, la hora que el administrador marca.

## Escenario 2: cambio de horario

Dado que el administrador modifica la hora de inicio de un turno y guarda, entonces el turno usa el horario nuevo: el horario guardado es el que aparece en la pantalla de configurar y el que se muestra al abrir caja.

## Escenario 3: apertura por turno

Dado que un cajero abre caja, cuando elige el turno, entonces solo puede elegir los turnos configurados. Un turno que no existe se rechaza con «Elige un turno de la lista.».

## Evidencias y definición de hecho

- La prueba automática cubre los tres escenarios: `docker compose exec app composer test` (198 pruebas).
- La pantalla se probó en escritorio y en el ancho de un teléfono (la grilla de turnos pasa de dos columnas a una) sobre `docker compose up`.
- La revisión en vivo, el aviso flotante, el modal de alta, el nombre que se escribe y el borrado con el botón de la papelera se comprobaron en un navegador real sobre el contenedor (`docker compose up`): el horario que no cierra bloquea el guardado, el nombre repetido se avisa bajo la ficha, la papelera abre la confirmación con el nombre escrito y el turno desaparece al confirmar.
- Falta la revisión de otro integrante: adjuntar a la historia Jira las capturas de Configuración > Turnos, de la apertura de caja y de la bitácora, y registrar quién lo probó.

Este documento describe el comportamiento del código y el procedimiento de QA. No incorpora capturas ni certifica por sí solo las pruebas en un dispositivo físico o la revisión de otro integrante.

## Bitácora: tipo de actividad

La actividad de los turnos quedó más precisa:

| Tipo de actividad | Cuándo se registra | Entidad | Detalle |
| --- | --- | --- | --- |
| `config.turnos` | Se guarda un horario nuevo | `Turno: <nombre>` | `11:00 a 16:00 → 11:30 a 15:30` |
| `config.turnos.nombre` | Se cambia el nombre de un turno | `Turno: <nombre nuevo>` | `Nombre: Medio día → Mediodía` |
| `config.turnos.agregar` | Se agrega un turno | `Turno: <nombre>` | `Clave: madrugada · Nombre: Madrugada · Horario: 06:00 a 09:00` |
| `config.turnos.eliminar` | Se elimina un turno | `Turno: <nombre>` | `Nombre: Noche · Horario: 18:00 a 23:59` |

La fila guarda el nombre del turno en el momento de la acción (columna `entidad_nombre`), así que la tabla sigue diciendo de qué turno se trataba aunque ese turno ya se haya borrado o se le haya cambiado el nombre después.

## Correcciones de esta entrega

- `ConfigController::turnos()` tenía un bloque `foreach` mal cerrado: era un error de sintaxis de PHP y ninguna pantalla del sistema cargaba.
- El commit anterior quitó el alta y el borrado de turnos de la pantalla; se restauraron.
- El alta y el borrado se reorganizaron: el alta va en un modal (la clave y el orden los pone el sistema) y el borrado es un botón por turno con confirmación. Se quitó el marcar y «Eliminar marcados».
- La pantalla de turnos se rehízo por pedido del administrador: ficha por turno con nombre, orden y horario escrito como en la pantalla de abrir caja; resumen del día que se rehace solo; errores que salen al escribir y avisos flotantes cuando algo no se puede hacer por regla del sistema (último turno y turno con cajas).
- La regla del turno en curso se quitó por pedido del administrador y después se quitó también la insignia «En curso»: ningún turno está bloqueado, el horario y el nombre se cambian a cualquier hora y la papelera borra igual.
- El nombre del turno ahora se escribe en la ficha: se le puede cambiar, no puede quedar vacío ni repetirse, y el cambio queda en la bitácora como `config.turnos.nombre`.
- La bitácora guardaba la entidad con el número del turno («Turno: #8») cuando ese turno ya no existía. Ahora cada fila guarda el nombre del turno en el momento de la acción (columna `entidad_nombre`) y la tabla lo muestra siempre, aunque el turno después se borre o se renombre.
- Se quitaron de la ficha el conteo de horas y la franja del día: el horario se lee igual que en la pantalla de abrir caja y sin contadores de más.
- La medianoche se veía como 24:00 (la forma interna de la base) en vez de 23:59 como la marcaba el administrador. Ahora todas las pantallas —Configuración, Caja, bitácora y avisos— muestran la hora tal como está marcada; la base sigue guardando 24:00:00.
- El modal de alta ya no trae los atajos Mañana, Día, Tarde y Noche: se escribe el nombre. El campo de orden se ve pero no se edita.
- `config.js` declaraba las funciones de turnos dentro de un bloque; en modo estricto quedan encerradas en ese bloque y el botón de la papelera se quedaba sin hacer nada. Se sacaron al nivel del archivo.
- La acción del formulario se deduce del botón enviado. Antes se deducía de qué campos venían, así que un alta fallida caía en la rama de edición y pisaba los horarios.
- `Turno::tieneCajasHoy()` usaba la hora del sistema y no el reloj de la aplicación, por lo que la regla «ya tiene cajas hoy» no se cumplía en las pruebas ni con la hora de Bolivia.
- La bitácora mostraba la entidad como «Turnos», sin decir de qué turno se trataba.
