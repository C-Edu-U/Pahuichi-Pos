# HANDOFF: Pahuichi POS (primera versión)

Documento de traspaso para iniciar el desarrollo. Léelo completo antes de escribir código. Si algo contradice al prompt de arranque, manda este documento. Si algo no está definido aquí, pregunta antes de inventar.

Fecha de redacción: 6 de octubre de 2026. Zona horaria del proyecto: America/La_Paz (UTC-4, sin horario de verano).

---

## 1. Contexto

- **Qué es:** punto de venta web responsive para un local de comida típica cruceña (Santa Cruz de la Sierra, Bolivia). Proyecto académico de Ingeniería de Software II.
- **Duración:** 3 semanas, 3 sprints de 1 semana. Metodología Scrumban (sprints Scrum con tablero Kanban y límites WIP).
- **Cliente, PMO y Product Owner:** el docente de la materia. Prioriza el backlog y acepta o rechaza cada historia en la Review del viernes.
- **Equipo:**
  - Carlos Eduardo Ugarteche Virhuez: Lead Dev, UI/UX.
  - Eleazar Josue Gutierrez Loza: Dev y QA. Caja, pagos y reportes.
  - Jefferson Gabriel Poeta Duran: Dev y QA. Facturación.
  - Katherine Jiménez Rojas: Scrum Master.
- **Objetivo del MVP:** el cajero registra una venta, cobra y entrega la factura imprimible en menos de 1 minuto; el analista ve las ventas del día; el sysadmin gestiona catálogo, usuarios y el QR vigente.

## 2. Restricciones técnicas (no negociables)

- PHP compatible con 8.0 (sin enums ni propiedades readonly). MVC propio, sin framework.
- HTML, CSS y JavaScript propios. **Sin frameworks de frontend, sin Tailwind, sin build, sin npm en producción.** Todo se sube por FTP.
- MariaDB/MySQL en local (XAMPP o Laragon) y en el hosting. SQLite solo para pruebas rápidas. Un solo esquema SQL.
- PDO con sentencias preparadas. Dinero en `DECIMAL(10,2)`, nunca `FLOAT`.
- Hosting gratuito (por ejemplo InfinityFree): **no hay cron, no hay mail(), no hay consola, puede faltar bcmath, gmp o gd**. Composer solo en local; `vendor/` se sube por FTP.
- Zona horaria fijada en PHP (`date_default_timezone_set('America/La_Paz')`) y en cada conexión (`SET time_zone = '-04:00'`).
- Nada de refrescos automáticos frecuentes en el POS (el hosting suspende por exceso de peticiones).

## 3. Arquitectura

### 3.1 Estructura de carpetas

```
pahuichi-pos/
  public/                 única carpeta expuesta a la web
    index.php             front controller
    .htaccess             URLs amigables hacia index.php
    assets/               css/, js/, img/
  app/
    Core/                 Router, Controller, Model, View, DB, Auth, Csrf
    Middleware/           Auth, Rol, Csrf, VigenciaQr
    Controllers/          Auth, Pos, Venta, Factura, Caja, Producto, Qr,
                          Reporte, Usuario, Config
    Models/               Usuario, Producto, Venta, Factura, TurnoCaja,
                          QrPago, Cliente
    Services/             CufService, NumeroALetras, FacturaBuilder,
                          CobroService, VigenciaQrService, ReporteService
    Views/
      layouts/            landing.php, panel.php (menú lateral y barra superior)
      landing/ auth/ pos/ ventas/ facturas/ caja/ productos/ qr/
      reportes/ usuarios/ config/ bitacora/
  config/                 config.php, permisos.php, config.local.php (fuera de Git)
  database/               schema.sql, seeds.sql
  storage/                qr/ (imágenes privadas), logs/
  vendor/
```

Si el hosting obliga a poner todo dentro de `htdocs`, cada carpeta que no sea `public/` lleva un `.htaccess` con `Require all denied`.

### 3.2 Flujo de una petición

1. `.htaccess` envía toda URL a `public/index.php`.
2. El Router resuelve controlador y acción.
3. Middlewares: sesión, rol, CSRF y vigencia del QR.
4. El controlador usa Modelos y Servicios.
5. La vista se pinta dentro del layout de landing o de panel.

### 3.3 Contratos entre áreas

Cada desarrollador lleva un área distinta. Se acuerdan el día 1 y se trabaja con un stub (versión simulada) hasta que exista el servicio real.

| De a | Qué entrega | Responsables |
|---|---|---|
| POS a Cobro | El POS entrega carrito y total; el modal de cobro devuelve método, monto recibido y cambio, o `qr_id` y referencia | Carlos (POS), Eleazar (CobroService) |
| Cobro a Factura | Con el pago confirmado, `FacturaBuilder` recibe venta, cliente y pago, y devuelve número, CUF y datos de impresión en la misma transacción | Eleazar, Jefferson |
| Reportes a Tablero | `ReporteService` entrega arreglos (ventas por día, producto, método de pago, hora); el tablero solo pinta | Eleazar (consultas), Carlos (tablero) |
| Permisos a Menú | `config/permisos.php` y el helper `puede()` deciden qué módulos ve cada rol | Eleazar (permisos), Carlos (menú) |

### 3.4 Seguridad mínima

- `password_hash` y `password_verify`; `session_regenerate_id` al iniciar sesión; cookies HttpOnly, SameSite=Lax y Secure.
- Token CSRF en todo formulario POST; toda salida escapada con un helper `e()`.
- Permisos verificados en el servidor en cada acción, no solo ocultando el menú.
- Bloqueo de 15 minutos tras 5 intentos fallidos de inicio de sesión.
- Subidas: tipo real con `finfo`, máximo 2 MB, nombre aleatorio, guardado fuera de `public/`.
- Día 1: revisar `phpinfo()` del hosting (versión, pdo_mysql, mbstring, gd, bcmath o gmp) y luego borrarlo.

## 4. Roles de usuario y módulos del panel

Panel con menú lateral estilo WordPress, 11 módulos, filtrado por rol. Los permisos viven en `config/permisos.php` (por ejemplo `'pos.vender' => ['cajero','sysadmin']`) y se consultan con `puede('pos.vender')`.

| Módulo | Qué hace | Cajero / operario | Analista | Sysadmin |
|---|---|---|---|---|
| Escritorio | Resumen según el rol | Su turno | Ventas del día | Todo y alertas |
| Punto de venta | Vender y cobrar | Sí | No | Sí |
| Caja | Apertura, cierre, arqueo | Su caja | Ver | Sí |
| Ventas y facturas | Listado, reimpresión, anulación | Ver las suyas y reimprimir | Ver todas | Sí, y anular |
| Clientes | NIT o CI frecuentes | Buscar y crear | Ver | Sí |
| Productos y categorías | Menú, precios, fotos, código SIN | Marcar «agotado hoy» | Ver | Sí |
| Pagos QR | Subir QR, vigencia, historial | Ver estado | Ver historial | Sí |
| Reportes | Ventas, platos top, Libro de Ventas | No | Sí, y exportar | Sí |
| Usuarios | Altas, roles, reseteo de clave | No | No | Sí |
| Configuración | Datos fiscales, leyendas, catálogos SIN | No | No | Sí |
| Bitácora | Quién hizo qué y cuándo | No | Ver | Sí |

Pantalla de entrada tras el login: cajero al Punto de venta, analista a Reportes, sysadmin al Escritorio.

**Landing pública:** una sola página con nombre y foto del local, horario, 3 o 4 platos destacados y el botón Iniciar sesión.

## 5. Modelo de datos (12 tablas)

La factura guarda un snapshot de sus datos para que la reimpresión salga idéntica aunque luego cambien precios o datos de la empresa. Nada se borra físicamente: ventas, facturas y QR cambian de estado.

| Tabla | Campos clave | Notas |
|---|---|---|
| usuarios | nombre, usuario, password_hash, rol, activo, intentos_fallidos, bloqueado_hasta | Rol: cajero, analista, sysadmin |
| empresa | nit, razon_social, direccion, municipio, telefono, codigo_sucursal, codigo_punto_venta, actividad_economica, modalidad, iva_transparente | Una sola fila; sucursal 0 = casa matriz |
| categorias | nombre, orden, activo | Platos, horneados, bebidas, postres |
| productos | codigo_interno, nombre, categoria_id, precio, codigo_producto_sin, actividad_economica, unidad_medida_sin, imagen, activo, disponible_hoy | Homologado con el catálogo del SIN |
| clientes | codigo_cliente, tipo_documento, numero_documento, complemento, razon_social, email | Tipo de documento 1 a 5 según catálogo |
| turnos_caja | usuario_id, abierto_en, monto_inicial, cerrado_en, efectivo_declarado, efectivo_esperado, diferencia | No se vende sin turno abierto |
| ventas | turno_id, usuario_id, cliente_id, fecha, subtotal, descuento, total, metodo_pago, monto_recibido, cambio, qr_id, referencia_pago, estado | Estado: pagada o anulada |
| venta_detalle | venta_id, producto_id, descripcion, cantidad, precio_unitario, descuento, subtotal | Descripción y precio copiados al vender |
| facturas | venta_id, numero, cuf, cufd, fecha_emision, leyenda, monto_total, monto_sujeto_iva, estado, motivo_anulacion, anulada_por, anulada_en, snapshot_json | `numero` único por punto de venta |
| qr_pagos | archivo, sha256, monto, etiqueta_banco, subido_por, subido_en, vence_en, estado, retirado_en | Estado: activo, vencido, reemplazado, retirado |
| catalogos_sin | tipo, codigo, descripcion | Tipos de documento, métodos de pago, unidades, motivos de anulación, leyendas, actividades |
| bitacora | usuario_id, accion, entidad, entidad_id, detalle, ip, creado_en | Ventas, anulaciones, QR, usuarios, configuración |

Reglas de integridad:

- El número de factura se toma dentro de una transacción con `SELECT ... FOR UPDATE` sobre una tabla de correlativos, para que dos cajeros nunca obtengan el mismo número.
- Venta, detalle y factura se guardan en la misma transacción: todo o nada.

### Datos semilla (precios de ejemplo, editables)

| Categoría | Producto | Bs |
|---|---|---|
| Platos | Majadito de charque | 30 |
| Platos | Locro carretero | 28 |
| Platos | Keperí al horno | 45 |
| Platos | Pacumutu | 50 |
| Platos | Arroz con queso | 20 |
| Horneados | Cuñapé | 3 |
| Horneados | Pan de arroz | 4 |
| Horneados | Empanada de arroz | 5 |
| Horneados | Humintas al horno | 6 |
| Horneados | Sonso de yuca | 10 |
| Horneados | Masaco de plátano | 15 |
| Bebidas | Somó | 8 |
| Bebidas | Chicha cruceña | 8 |
| Bebidas | Mocochinchi | 7 |
| Prueba | Producto de prueba QR | 0,50 |

Usuarios demo: uno por rol (cajero, analista, sysadmin) con claves de desarrollo que se cambian antes de la demo.

## 6. Facturación con lineamientos del SIN (simulada)

Se implementa la **Factura de Compra y Venta (código de documento sector 1)** según la RND 102100000011, que sigue vigente. Todo se calcula como en un sistema real; solo el CUFD es simulado y la representación gráfica lleva la marca **«DOCUMENTO DE PRUEBA, SIN VALIDEZ FISCAL»**.

### 6.1 Contenido de la factura

| Bloque | Campos |
|---|---|
| Emisor | NIT, razón social, casa matriz o sucursal N°, punto de venta N°, dirección, teléfono, municipio |
| Identificación | N° de factura (correlativo), código de autorización (CUF), fecha y hora de emisión |
| Cliente | Nombre o razón social, tipo y número de documento (NIT, CI, CEX, PAS, OD), complemento, código de cliente |
| Detalle por línea | Código interno, descripción, cantidad, unidad de medida, precio unitario, descuento, subtotal |
| Totales | Subtotal, descuento, total, monto gift card, monto a pagar, importe base para crédito fiscal, monto en literal («Son: Treinta y cinco 00/100 Bolivianos») |
| Pie | Leyenda fija «ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY», leyenda de la Ley N° 453, texto de representación gráfica y código QR |
| Datos internos | Código de producto SIN, actividad económica, método de pago, moneda Bs con tipo de cambio 1, usuario emisor, CUFD |

El código de producto SIN va en los datos de la factura; en la impresión se muestra el código interno del local.

### 6.2 Reglas a programar

1. **CUF:** se rellenan con ceros y se concatenan: NIT (13), fecha y hora `yyyyMMddHHmmssSSS` (17), sucursal (4), modalidad (1), tipo de emisión (1), tipo de factura (1), documento sector (2), número de factura (10) y punto de venta (4). Se agrega un dígito verificador Módulo 11, la cadena se convierte a Base 16 y se le añade el código de control del CUFD.
2. **Base 16 sin extensiones:** la cadena de unos 54 dígitos no cabe en un entero de PHP. Convertir por división larga sobre texto (no asumir bcmath ni gmp).
3. **CUFD simulado:** el sistema genera uno ficticio que rota cada 24 h.
4. **Numeración:** correlativa, sin saltos ni repetidos por punto de venta.
5. **Nominatividad:** toda factura lleva número de documento del comprador, cualquiera sea el monto. Códigos especiales: 99001 (embajadas, consulados), 99002 (control tributario), 99003 (ventas menores del día), enviados con tipo de documento NIT y código de excepción 1. El botón «Sin datos» del POS usa razón social S/N; el número que asigna queda configurable (pendiente de validar con el docente).
6. **Ventas menores a Bs 5:** no es obligatorio facturarlas salvo que el cliente lo pida. Se registran y al cierre se emite una factura resumen con 99003 y la leyenda «VENTAS MENORES DEL DÍA».
7. **Sin facturas en cero.**
8. **Leyenda Ley 453:** cambia aleatoriamente en cada emisión, tomada del catálogo según la actividad económica.
9. **QR de verificación:** `https://siat.impuestos.gob.bo/consulta/QR?nit=...&cuf=...&numero=...&t=1` (t = 1 rollo, 2 media hoja), mínimo 3 x 3 cm. En modo demo usar el dominio piloto `pilotosiat.impuestos.gob.bo`.
10. **Método de pago:** código del catálogo del SIN. Efectivo es 1; «Otros» (5) solo si no existe código para el medio real. El código para QR se toma del catálogo vigente al cargar las semillas.
11. **Redondeo:** 2 decimales, mitad hacia arriba, por línea y en el total.
12. **Anulación:** solo sysadmin, con motivo del catálogo. Hasta el día 9 del mes siguiente se anula directo; después se marca «requiere trámite ante el SIN» (RND 102600000025).
13. **Representación gráfica:** dos plantillas, rollo de 80 mm y media hoja, con marca de agua de documento de prueba. Impresión con CSS `@media print`.
14. **IVA Transparente (RND 102600000034, Ley 1733):** guardar el precio con IVA, calcular neto e IVA (13 %) por línea y dejar un bloque «IVA» en la plantilla, activable desde Configuración.
15. **Homologación:** cada producto lleva código SIN y actividad económica desde el sprint 1 (plazo del SIN: 30 de octubre de 2026).

Referencias oficiales (leer antes del sprint 2): páginas SIAT de Generación CUF, Algoritmo Módulo 11, Código QR, Factura de Compra y Venta, Emisión y envío, Pruebas de inspección y Homologación.

## 7. Pagos: efectivo y QR de 24 h

### Efectivo

- El cajero escribe el monto recibido o toca un billete rápido (Bs 10, 20, 50, 100, 200); el sistema calcula el cambio.
- No se confirma si el monto recibido es menor que el total.
- Efectivo esperado del turno = monto inicial + ventas en efectivo; el arqueo lo compara con lo contado y guarda la diferencia.

### QR (imagen subida por el sysadmin)

1. Solo hay un QR activo a la vez; subir uno nuevo pasa el anterior a «Reemplazado».
2. Al subir: validar tipo real de imagen, calcular SHA-256 y **rechazar si coincide con un QR anterior** (obliga a usar imagen nueva).
3. `vence_en = subido_en + 24 h`, hora de Bolivia.
4. **Sin cron:** en cada petición un middleware ejecuta `UPDATE qr_pagos SET estado='vencido' WHERE estado='activo' AND vence_en <= NOW()`.
5. La imagen vive en `storage/qr/` y la sirve un controlador que solo entrega el QR activo. Uno vencido responde 410.
6. Aviso en el panel 2 horas antes del vencimiento (configurable).
7. Con el QR vencido, el botón «Cobrar con QR» del POS se deshabilita con el mensaje «QR vencido: pide al administrador uno nuevo». El sysadmin es llevado a Pagos QR y solo puede subir uno nuevo o cerrar sesión.
8. Botón «Retirar ahora» para sacar un QR de circulación antes de tiempo.
9. Historial: quién subió cada QR, cuándo, cuánto estuvo activo y cuántas ventas cobró.

Flujo de cobro con QR: el cajero toca «Cobrar con QR», se muestra el QR a pantalla completa con monto y hora de vencimiento, el cliente paga en su app bancaria, el cajero verifica la notificación del banco, registra la referencia y confirma. La venta guarda `qr_id` y la referencia.

**QR de 0,50 Bs:** un QR de monto fijo solo cobra ese monto. Para la demo se usa el «Producto de prueba QR» de Bs 0,50; si el total de una venta no coincide con el monto del QR, el POS advierte antes de confirmar. El monto del QR es un dato configurable.

## 8. Backlog completo (29 historias, 92 puntos)

Prioridad MoSCoW. Las Should y Could son las primeras en recortarse.

| ID | Historia | Épica | Responsable | Prioridad | Pts | Sprint |
|---|---|---|---|---|---|---|
| HU-01 | Base MVC: front controller, router, layouts, conexión PDO y configuración | E1 | Carlos | Must | 5 | 1 |
| HU-02 | Layout del panel, menú lateral estilo WordPress y sistema de diseño base | E3 | Carlos | Must | 3 | 1 |
| HU-03 | Despliegue en el hosting y guía en el README | E1 | Carlos | Must | 2 | 1 |
| HU-04 | Esquema de BD y datos semilla: usuarios demo, menú cruceño, catálogos SIN | E1 | Eleazar | Must | 3 | 1 |
| HU-05 | Inicio y cierre de sesión seguros, bloqueo tras 5 intentos | E2 | Eleazar | Must | 3 | 1 |
| HU-06 | Permisos por rol: middleware, 403 y menú filtrado | E2 | Eleazar | Must | 3 | 1 |
| HU-07 | Gestión de categorías y productos con foto, precio, código SIN, actividad y unidad | E7 | Jefferson | Must | 5 | 1 |
| HU-08 | Servicio CUF (Módulo 11 y Base 16) con prueba automatizada contra el ejemplo del SIAT | E7 | Jefferson | Must | 3 | 1 |
| HU-09 | POS: grilla por categoría con búsqueda y carrito | E3 | Carlos | Must | 5 | 2 |
| HU-10 | Landing simple con botón Iniciar sesión | E3 | Carlos | Must | 2 | 2 |
| HU-11 | Gestión de usuarios: alta, rol, activar, resetear clave | E2 | Carlos | Must | 3 | 2 |
| HU-12 | Cobro en efectivo con cálculo de cambio | E4 | Eleazar | Must | 2 | 2 |
| HU-13 | Subir QR: vale 24 h, uno solo activo, no se repite imagen | E4 | Eleazar | Must | 3 | 2 |
| HU-14 | El QR vence solo, el POS bloquea el cobro QR y el panel exige uno nuevo | E4 | Eleazar | Must | 3 | 2 |
| HU-15 | Cobro por QR con referencia de pago | E4 | Eleazar | Must | 2 | 2 |
| HU-16 | Registrar o buscar cliente (NIT o CI, complemento, casos especiales) | E7 | Jefferson | Must | 3 | 2 |
| HU-17 | Emisión de factura: correlativo, CUF, CUFD simulado, leyendas, monto literal | E7 | Jefferson | Must | 5 | 2 |
| HU-18 | Datos fiscales de la empresa y leyendas | E7 | Jefferson | Must | 2 | 2 |
| HU-19 | Tablero del analista: ventas del día, platos top, ventas por hora | E6 | Carlos | Must | 3 | 3 |
| HU-20 | Bitácora de auditoría | E2 | Carlos | Should | 2 | 3 |
| HU-21 | Pulido UI/UX, accesibilidad y pruebas en móvil | E3 | Carlos | Must | 3 | 3 |
| HU-22 | Apertura y cierre de caja con arqueo; sin turno no se vende | E5 | Eleazar | Must | 5 | 3 |
| HU-23 | Reportes filtrables por fecha, cajero y método de pago; exportar CSV | E6 | Eleazar | Must | 5 | 3 |
| HU-24 | Factura impresa en rollo 80 mm y media hoja, con QR y marca de prueba | E7 | Jefferson | Must | 5 | 3 |
| HU-25 | Anulación de factura con motivo y control de plazo | E7 | Jefferson | Should | 3 | 3 |
| HU-26 | Libro de Ventas exportable con columnas del SIN | E7 | Jefferson | Should | 2 | 3 |
| HU-27 | Ventas menores a Bs 5 con factura resumen 99003 al cierre | E7 | Jefferson | Could | 3 | 3 |
| HU-28 | Marcar plato como «agotado hoy» | E7 | Jefferson | Could | 1 | 3 |
| HU-29 | Pruebas finales de regresión y ensayo de la demo | E8 | Todos | Must | 3 | 3 |

Puntos por sprint: 27, 30 y 35.

## 9. ALCANCE DE LA PRIMERA VERSIÓN: Sprint 1 (27 puntos)

Objetivo del sprint: **base desplegable**. Al terminar, existe una URL en el hosting donde se inicia sesión con cada rol, el menú cambia según el rol, el catálogo se administra y el servicio CUF pasa su prueba.

Orden de construcción recomendado (HU-01 bloquea casi todo, debe estar lista a mitad del sprint):

1. **HU-01** Base MVC: `public/index.php`, `.htaccess`, Router, Controller, View, DB (PDO), Auth, Csrf, helpers `e()` y `puede()`, `config/config.php` y `config.local.php.example`.
2. **HU-04** `database/schema.sql` con las 12 tablas y `database/seeds.sql` con catálogos SIN, categorías, productos, empresa y 3 usuarios demo.
3. **HU-05** Login y logout, hash de claves, regeneración de sesión, bloqueo de 15 min tras 5 intentos, CSRF.
4. **HU-06** Middleware de rol, página 403, `config/permisos.php` y menú lateral filtrado.
5. **HU-02** Layouts `landing.php` y `panel.php` con el menú lateral y el sistema de diseño (tokens CSS y componentes). Ver sección 10.
6. **HU-07** CRUD de categorías y productos (foto con validación `finfo`, 2 MB, nombre aleatorio; precio, código SIN, actividad económica, unidad de medida).
7. **HU-08** `CufService` con Módulo 11 y Base 16 por división larga, más una prueba automatizada (script PHP ejecutable por CLI) contra el ejemplo publicado por el SIAT.
8. **HU-03** README con instalación local y guía de despliegue por FTP; revisión de `phpinfo()`.

### Criterios de aceptación del sprint 1

- [ ] Tras 5 claves erróneas la cuenta queda bloqueada 15 minutos.
- [ ] Un cajero que escribe la URL de Usuarios recibe 403 y el intento queda registrado.
- [ ] Cada rol ve en el menú exactamente los módulos de la matriz de la sección 4.
- [ ] Todo formulario POST rechaza peticiones sin token CSRF.
- [ ] Subir un archivo que no es imagen o pasa de 2 MB es rechazado.
- [ ] El CUF calculado con los datos del ejemplo del SIAT coincide con el resultado publicado.
- [ ] Funciona a 360 px, 768 px y en escritorio.
- [ ] Corre en el hosting, no solo en local.

### Fuera del sprint 1

POS, cobros, QR, factura, caja, reportes y landing final. Se dejan carpetas y rutas preparadas, sin implementar.

## 10. Lineamientos de UI/UX

- **Identidad cruceña:** verde y blanco de la bandera de Santa Cruz como base, con un acento cálido (terracota o ámbar) que evoque el horno de barro. Sobrio, confiable, sin adornos.
- **Sistema de diseño primero:** tokens en `:root` (color, tipografía, espaciado en múltiplos de 8, radios, sombras), luego componentes (botón, campo, tarjeta de producto, tabla, insignia, alerta, modal, menú lateral), luego pantallas. CSS propio en `public/assets/css/`, sin estilos en línea.
- **POS táctil:** botones de 48 px o más; grilla de 2 columnas en móvil, 3 o 4 en tableta, 5 o 6 en escritorio; carrito fijo abajo en móvil y lateral en escritorio; total siempre visible.
- **Menú lateral estilo WordPress:** se pliega a íconos en tableta, hamburguesa en móvil; módulo activo resaltado; insignias de aviso (por ejemplo «QR vence en 2 h»).
- **Números:** cifras tabulares (`font-variant-numeric: tabular-nums`) en precios y totales, alineadas a la derecha en tablas. Jerarquía clara entre total, líneas y acciones.
- **Accesibilidad:** contraste AA, foco visible, etiquetas en todos los campos, errores junto al campo.
- **Mobile first:** 360, 768 y 1280 px. Modo claro.
- **Impresión:** `@media print` para rollo de 80 mm y media hoja.
- Las vistas son PHP en `app/Views`; el diseño toca marcado y clases, no la lógica.

## 11. Convenciones de trabajo

- Git: `main` es lo que está en el hosting; `develop` es integración; una rama por historia (`feature/HU-14-qr-vencimiento`). Todo entra por Pull Request a `develop` con 1 aprobación. Solo Carlos fusiona `develop` en `main` cada viernes.
- Commits con el ID: `HU-14: bloquea QR vencido en el POS`.
- Credenciales en `config/config.local.php`, fuera del repositorio (`.gitignore`).
- Código y comentarios técnicos: nombres de tablas, campos y clases en español sin tildes (como en el modelo de datos); textos de interfaz en español de Bolivia.
- Tablero Kanban con límites WIP: En progreso 3, En revisión 3, Pruebas QA 3.

### Definition of Done

- [ ] Criterios de aceptación cumplidos y probados por el QA cruzado.
- [ ] Pull Request revisado y fusionado en `develop` sin conflictos.
- [ ] Funciona a 360 px, 768 px y escritorio.
- [ ] Validación en servidor, CSRF y permiso verificados (probar la URL directa con otro rol).
- [ ] Sin errores ni avisos de PHP con `display_errors` activo en local.
- [ ] Desplegado y probado en el hosting.
- [ ] Acción sensible registrada en la bitácora, si corresponde.

## 12. Riesgos que afectan al código

| Riesgo | Mitigación |
|---|---|
| Hosting sin cron ni mail() | Vencimiento del QR evaluado en cada petición; factura impresa o descargada |
| Faltan bcmath, gmp, gd | Base 16 por división larga; QR de factura en SVG (por ejemplo chillerlan/php-qrcode) |
| Hora del servidor distinta | Zona America/La_Paz fijada en PHP y en cada conexión |
| Factura de prueba confundida con real | Marca «SIN VALIDEZ FISCAL» y dominio piloto en el QR |
| Integración tardía entre áreas | Contratos de la sección 3.3 y stubs desde el día 1 |
| Estilos inconsistentes al generar UI con IA | Un solo dueño del sistema de diseño (Carlos) |

## 13. Preguntas abiertas para el docente (PO)

1. ¿El QR de monto fijo de 0,50 Bs es solo para la demo o es el cobro real de todas las ventas?
2. ¿Qué número de documento usa el botón «Sin datos» del POS? (el plan propone 99002 o 99003, configurable).
3. ¿Se acepta el CUFD simulado y el dominio piloto en el QR de verificación?
4. ¿Qué fecha de inicio definitiva tiene el sprint 1? (el plan de ejemplo usa el lunes 12 de octubre de 2026).

Mientras no haya respuesta, aplicar el supuesto indicado en este documento y dejarlo configurable.
