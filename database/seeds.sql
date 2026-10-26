-- Pahuichi POS · datos de prueba
-- Se cargan solos la primera vez que arranca el sistema (base sin usuarios).
-- Contraseña de todos los usuarios con contraseña: Pahuichi2026
-- PIN de caja: Mariela 4821, Jorge 7350. PIN de autorización del administrador: 1234.

INSERT INTO usuarios (nombre, usuario, password_hash, pin_hash, rol) VALUES
  ('Ana Lucía Vaca Saucedo', 'admin', '$2y$10$qr8gN7/FBmf5.t3ngta8u.VRUgTfPKepMlmyErVo8EvNNHEaOvbY2', '$2y$10$qfno.3B/DwZb93q/YTspL.39fkTbPtCwMYXjiTgzCPTp.xb6VZCai', 'administrador'),
  ('Mariela Suárez Añez', 'mariela', '$2y$10$qr8gN7/FBmf5.t3ngta8u.VRUgTfPKepMlmyErVo8EvNNHEaOvbY2', '$2y$10$OsKsq.cqF99tiKjHi4KcYOxQ2GIK8cPY8UKd3aw2TZiMxs6WVAql6', 'cajero'),
  ('Jorge Rivero Chávez', 'jorge', '$2y$10$qr8gN7/FBmf5.t3ngta8u.VRUgTfPKepMlmyErVo8EvNNHEaOvbY2', '$2y$10$81KlWwQhtbn61LWpN5I9X.UYgZ4NEwbnvupCkFiEt/vR6Lf0cqhDm', 'cajero'),
  ('Luis Fernando Roca', 'luis', '$2y$10$qr8gN7/FBmf5.t3ngta8u.VRUgTfPKepMlmyErVo8EvNNHEaOvbY2', NULL, 'mesero'),
  ('Teresa Paz Justiniano', 'teresa', NULL, NULL, 'cocina');

INSERT INTO negocio (id, razon_social, nombre_comercial, nit, direccion, telefono, municipio) VALUES
  (1, 'PAHUICHI S.R.L.', 'Pahuichi', '1023456021', 'Calle por confirmar', NULL, 'Santa Cruz de la Sierra');

-- Valores simulados: no son credenciales reales del SIN
INSERT INTO parametros_fiscales (id, codigo_sucursal, codigo_punto_venta, actividad_economica, cuis, cufd, cufd_codigo_control, cufd_vigencia, numero_inicial, modalidad) VALUES
  (1, 0, 0, '561011 · Restaurantes y venta de comidas', 'C2F45A01', 'BQUFDdkRDQUE=MzQ1MDc2RkQ4M0Y5Nzg=', '7A3F2C91D4E8B605', CONCAT(CURDATE(), ' 23:59:00'), 1, 'computarizada');

INSERT INTO leyendas (texto) VALUES
  ('Ley N° 453: El proveedor deberá entregar el producto en las modalidades y términos ofertados o convenidos.'),
  ('Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.'),
  ('Ley N° 453: Está prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.'),
  ('Ley N° 453: Tienes derecho a un trato equitativo sin discriminación en la oferta de productos.'),
  ('Ley N° 453: El proveedor debe exhibir certificaciones de habilitación o documentos que acrediten la calidad de sus productos.'),
  ('Ley N° 453: Tienes derecho a la reposición o devolución del producto si no cumple lo ofertado.'),
  ('Ley N° 453: El proveedor debe brindar atención sin discriminación, con trato cordial y oportuno.'),
  ('Ley N° 453: Tienes derecho a denunciar las infracciones a tus derechos como consumidor.');

INSERT INTO turnos (clave, nombre, hora_inicio, hora_fin, orden) VALUES
  ('mediodia', 'Medio día', '11:00:00', '16:00:00', 1),
  ('noche', 'Noche', '18:00:00', '24:00:00', 2);

-- Colores de la paleta cerrada (config/paleta.php), todos con contraste AA y texto blanco
INSERT INTO categorias (nombre, orden, color) VALUES
  ('Platos', 1, '#8a5300'),
  ('Refrescos', 2, '#1062a8'),
  ('Sodas', 3, '#8e2c7c'),
  ('Vinos', 4, '#6e2a1e'),
  ('Postres', 5, '#3f6f1f'),
  ('Prueba', 6, '#4f5a54');

INSERT INTO productos (codigo, nombre, nombre_corto, categoria_id, precio, unidad, favorito, va_a_cocina, agotado) VALUES
  ('PL-01', 'Majadito de charque', 'Majadito', 1, 40.00, 'plato', 1, 1, 0),
  ('PL-02', 'Locro carretero', 'Locro', 1, 35.00, 'plato', 1, 1, 0),
  ('PL-03', 'Picante de pollo', 'Picante', 1, 45.00, 'plato', 1, 1, 0),
  ('PL-04', 'Keperí al horno', 'Keperí', 1, 55.00, 'plato', 1, 1, 0),
  ('PL-05', 'Pacumutu de res', 'Pacumutu', 1, 60.00, 'plato', 0, 1, 0),
  ('PL-06', 'Arroz con queso', 'Arroz c/ queso', 1, 25.00, 'plato', 0, 1, 0),
  ('PL-07', 'Fricasé de cerdo', 'Fricasé', 1, 40.00, 'plato', 0, 1, 1),
  ('PL-08', 'Sopa de maní', 'Sopa de maní', 1, 20.00, 'plato', 0, 1, 0),
  ('PL-09', 'Sonso de yuca', 'Sonso', 1, 15.00, 'porcion', 1, 1, 0),
  ('RE-01', 'Mocochinchi', 'Mocochinchi', 2, 10.00, 'vaso', 1, 0, 0),
  ('RE-02', 'Somó', 'Somó', 2, 10.00, 'vaso', 0, 0, 0),
  ('RE-03', 'Chicha cruceña', 'Chicha', 2, 10.00, 'vaso', 1, 0, 0),
  ('RE-04', 'Limonada', 'Limonada', 2, 10.00, 'vaso', 0, 0, 0),
  ('RE-05', 'Jarra de mocochinchi', 'Jarra moco.', 2, 25.00, 'jarra', 0, 0, 0),
  ('SO-01', 'Soda cola 500 ml', 'Cola 500', 3, 8.00, 'botella', 1, 0, 0),
  ('SO-02', 'Soda naranja 500 ml', 'Naranja 500', 3, 8.00, 'botella', 0, 0, 0),
  ('SO-03', 'Soda cola 2 L', 'Cola 2 L', 3, 18.00, 'botella', 0, 0, 0),
  ('SO-04', 'Agua sin gas 500 ml', 'Agua', 3, 6.00, 'botella', 0, 0, 0),
  ('VI-01', 'Vino tinto, copa', 'Copa tinto', 4, 20.00, 'copa', 0, 0, 0),
  ('VI-02', 'Vino tinto de Tarija, botella', 'Tinto bot.', 4, 120.00, 'botella', 0, 0, 0),
  ('VI-03', 'Vino blanco, copa', 'Copa blanco', 4, 20.00, 'copa', 0, 0, 0),
  ('PO-01', 'Arroz con leche', 'Arroz c/ leche', 5, 12.00, 'porcion', 0, 0, 0),
  ('PO-02', 'Leche asada', 'Leche asada', 5, 12.00, 'porcion', 0, 0, 0),
  ('PO-03', 'Helado de canela', 'Helado', 5, 10.00, 'porcion', 0, 0, 0),
  ('PR-01', 'Producto de prueba QR', 'Prueba QR', 6, 0.50, 'unidad', 0, 0, 0);
