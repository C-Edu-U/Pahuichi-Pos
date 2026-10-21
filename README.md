# Pahuichi POS

Punto de venta web para un restaurante de comida típica cruceña. Está pensado para la hora pico: una venta de tres ítems cobrada en efectivo en 15 segundos o menos, y una venta favorita con cobro exacto en 4 toques o menos.

PHP 8.2 con MVC propio, MariaDB 11 y una interfaz en HTML, CSS y JavaScript propios (PWA). Todo se levanta con `docker compose up`.

## Instalación

Requisitos: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows o macOS) o Docker Engine con Compose (Linux).

```bash
git clone https://github.com/C-Edu-U/Pahuichi-Pos.git
cd Pahuichi-Pos
docker compose up --build
```

La primera vez tarda unos minutos: construye la imagen de PHP, instala las dependencias, crea las tablas y carga los datos de prueba. Cuando el log muestre `resuming normal operations`, abre **https://localhost:8443**.

- El certificado es autofirmado: el navegador avisa la primera vez («Avanzado» → «Continuar»). Para uno de confianza en la red local, ver [docker/certs/LEEME.md](docker/certs/LEEME.md).
- Desde otra PC, tablet o celular de la misma red: `https://<IP-de-esta-PC>:8443`.
- Para cambiar puertos o claves, copia `.env.example` como `.env` y edítalo.

### Comandos útiles

| Qué | Comando |
|---|---|
| Levantar en segundo plano | `docker compose up -d` |
| Ver el log | `docker compose logs -f app` |
| Detener | `docker compose down` |
| Volver a los datos de prueba | `docker compose exec app php bin/consola.php reiniciar` |
| Borrar todo, incluida la base | `docker compose down -v` |
| Pruebas automáticas | `docker compose exec app composer test` |
| Estado del sistema | https://localhost:8443/salud |

La base también se puede revisar con un cliente SQL en `127.0.0.1:3307` (usuario `pahuichi`, clave `pahuichi_local`, salvo que `.env` diga otra cosa).

## Usuarios de prueba

| Rol | Cómo entrar | Pantalla de entrada |
|---|---|---|
| Cajero | PIN **4821** (Mariela) o **7350** (Jorge) en «Caja con PIN» | Punto de venta |
| Administrador | Usuario `admin`, contraseña `Pahuichi2026` | Tablero |
| Mesero | Usuario `luis`, contraseña `Pahuichi2026` | Pedidos |
| Cocina | Usuario `teresa` | No entra al panel: recibe la comanda impresa |

Los cajeros también tienen la contraseña `Pahuichi2026`. El PIN de autorización del administrador es `1234`: autoriza el descuento manual y lo usará la anulación.

## Estado

| Historia | Qué hace | Dónde |
|---|---|---|
| HU-01 | `docker compose up` con HTTPS, base MVC, migraciones y datos de prueba | todo el proyecto |
| HU-02 | Login con contraseña o PIN, bloqueo de 15 min tras 5 fallos, cierre por inactividad salvo el cajero | `/login` |
| HU-03 | Alta de usuarios, rol, activar y desactivar, desbloquear, reseteo de contraseña y PIN | `/usuarios` |
| HU-04 | Permisos por rol en cada ruta, 403 con registro en la bitácora y menú filtrado | `config/permisos.php` |
| HU-08 | Datos del negocio con logo | `/config#negocio` |
| HU-09 | Parámetros fiscales simulados y aviso de CUFD vencido | `/config#fiscal` |
| HU-11 | Horario de los turnos | `/config#turnos` |
| HU-13 | Categorías con orden y color de la paleta | `/catalogo/categorias` |
| HU-14 | Productos; favorito y agotado de un toque | `/catalogo/productos` |
| HU-20 | Abrir caja con turno, cajero y fondo; una sola caja abierta | `/caja` |
| HU-21 | Sin caja abierta, el POS muestra «Abrir caja» | `/pos` |
| HU-05 | Bitácora de solo lectura con filtros por fecha, usuario, acción y acciones sensibles | `/bitacora` |
| HU-28 | Cuadrícula del POS: favoritos primero, categorías con su color, búsqueda por nombre o código; agotado visible sin poder venderse | `/pos` |
| HU-29 | Carrito: un toque agrega y otro suma; +, − y quitar por línea; total siempre visible; cancelar pedido con confirmación | `/pos` |
| HU-10 | Leyendas de la Ley 453: agregar, activar y desactivar con mínimo 8 activas; `Leyenda::alAzar()` elige una para cada factura | `/config#leyendas` |
| HU-06 | Descuento manual con el PIN del administrador: permiso de un solo uso por 5 minutos, bloqueo tras 5 PIN errados, menor que el subtotal; venta, factura y bitácora guardan monto, motivo y quién autorizó | `/pos` |
| HU-30 | Tipo de pedido: mesa con su número (1 a 99, sin valor por defecto) o para llevar; la comanda dice «MESA 4» o «PARA LLEVAR» | `/pos` |
| HU-31 | Nota por ítem para cocina (hasta 60 caracteres, Enter la guarda): sale solo en la comanda; las bebidas y lo que no va a cocina no la ofrecen | `/pos` |
| HU-33 | Atajos del POS: F2 buscar, F4 cobrar, F8 efectivo exacto, Esc cancelar con confirmación; cada tecla se ve en su botón | `/pos` |

El resto de los módulos muestra «Próximamente» con sus historias. Toda acción sensible queda en la bitácora; al registrar una acción nueva, agrégala a `config/bitacora.php` para que se vea con su nombre.

### Guía para el QA

Los escenarios Gherkin del Sprint 1 tienen prueba automática en `tests/Integracion/` (`docker compose exec app composer test`). Para revisarlos a mano:

1. **Instalación limpia:** `docker compose down -v` y `docker compose up --build`; luego https://localhost:8443/salud muestra la base migrada con 5 usuarios y 25 productos.
2. **Login del cajero con PIN:** PIN 4821 → entra al punto de venta.
3. **Bloqueo por intentos:** usuario `luis` con una contraseña errada 5 veces → bloqueado hasta dentro de 15 minutos. Como `admin`, en Usuarios aparece «Bloqueado hasta» y se puede desbloquear.
4. **Permiso validado en el servidor:** como cajero, escribe a mano https://localhost:8443/usuarios → «acceso denegado» sin datos.
5. **Sin caja no se vende:** como cajero, el punto de venta muestra «Abrir caja».
6. **Una sola caja:** con dos navegadores (o una ventana de incógnito), deja a Jorge (PIN 7350) en «Abrir caja» con el turno de noche. En el otro, abre la caja de medio día con Mariela. Luego envía el formulario de Jorge → el sistema lo impide y explica que primero se debe cerrar y arquear la caja de medio día.
7. **Mesa o para llevar:** agrega un plato y pulsa F8 sin escribir la mesa → no cobra, marca el campo y pide el número. Escribe 4 y cobra: en «Ver tickets» la comanda dice «MESA 4». El pedido siguiente vuelve a pedir la mesa; con «Llevar» la comanda dice «PARA LLEVAR».
8. **Nota para cocina:** agrega un Majadito y un Mocochinchi. Solo el Majadito tiene el botón de nota: escribe «sin cebolla» y pulsa Enter → la nota queda bajo el plato. Cobra y abre «Ver tickets»: la comanda dice «» SIN CEBOLLA»; la copia de caja y la factura no muestran la nota.
9. **Descuento manual:** con dos Majaditos (Bs 80), «Descuento» → Bs 10, motivo y el PIN 4821 de Mariela → no autoriza y avisa cuántos intentos quedan. Con el PIN 1234 → el pedido muestra subtotal 80, descuento 10 y total 70. Cobra: la copia de caja y la factura muestran el descuento, y en la Bitácora aparece «Descuento manual» con quién autorizó. Un descuento de Bs 80 o más no se acepta.
10. **Atajos del POS:** con la caja abierta, F2 lleva el foco a la búsqueda. Agrega dos productos: F4 abre el cobro y Esc lo cierra sin perder el pedido. Esc dentro de una nota la cierra, Esc en una búsqueda con texto la borra y, después, Esc pide confirmar la cancelación con «Seguir vendiendo» enfocado (Enter no borra el pedido). F8 cobra en efectivo exacto.

Para repetir las pruebas desde cero: `docker compose exec app php bin/consola.php reiniciar`. El CUFD de los datos de prueba vence a las 23:59 del día en que se cargaron; al día siguiente la configuración lo muestra como vencido, que es lo esperado.

## Arquitectura

```
public/                 única carpeta expuesta
  index.php             front controller: toda petición entra por aquí
  assets/               CSS, JS, fuentes e íconos
app/
  Core/                 App, Router, Controller, Vista, DB, Sesion, Csrf, Migrador, Reloj
  Middleware/           filtros antes del controlador (CSRF en todo POST)
  Controllers/          reciben la petición y eligen la vista
  Models/               consultas a la base, con sentencias preparadas
  Services/             reglas de negocio
  Views/                plantillas PHP: layouts/ y una carpeta por módulo
  rutas.php             todas las rutas
config/                 app.php, modulos.php (menú), permisos y paleta
database/
  migraciones/          .sql numeradas; se aplican solas al arrancar
  seeds.sql             datos de prueba, solo si la base está vacía
docker/                 imagen de PHP con Apache y HTTPS, arranque e init de MariaDB
tests/                  PHPUnit: Unit/ e Integracion/
demo/                   demo visual estática de todas las pantallas (referencia)
```

Flujo de una petición: `public/index.php` → `Router` → middleware → `Controller` → `Model` o `Service` → `Vista` dentro de su layout.

- Los módulos que aún no tienen su historia implementada muestran la pantalla «Próximamente» con sus historias (`config/modulos.php`).
- Cada cambio de esquema es una migración nueva en `database/migraciones/`; las ya aplicadas no se editan.
- Toda salida variable de las vistas pasa por `e()`; todo formulario POST lleva `csrf_campo()`.

## Demo visual

`demo/` guarda la demo estática con todas las pantallas del producto final y datos simulados en el navegador. Es la referencia para portar cada pantalla a su vista PHP. Se abre directo: `demo/login.html`. Sus páginas del panel se arman con [herramientas/panel](herramientas/panel/LEEME.md).

Más detalle del diseño en [DESIGN.md](DESIGN.md).

## Licencias de terceros

- Íconos: [Tabler Icons](https://tabler.io/icons), MIT.
- Tipografía de marca: Archivo, SIL Open Font License.
- Generador de QR de la demo: [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) de Kazuhiko Arase, MIT.
- Imagen de PHP: [php](https://hub.docker.com/_/php) oficial; extensiones con [docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer), MIT.
- Base de datos: [MariaDB](https://mariadb.org/), GPL v2.
- Pruebas: [PHPUnit](https://phpunit.de/), BSD-3.
