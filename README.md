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
