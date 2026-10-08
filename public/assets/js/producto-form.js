/* Producto (demo). La foto se recorta cuadrada y se comprime a 200 KB o
   menos en el navegador (con servidor, se valida el tipo con finfo y se
   guarda con nombre aleatorio). Los cambios de precio quedan en el historial. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };
  var MAX_BYTES = 200 * 1024;
  var modificadores = [];

  D.categorias.forEach(function (c) {
    var o = document.createElement('option');
    o.value = c.id;
    o.textContent = c.nombre;
    $('categoria').appendChild(o);
  });

  /* Modo edición */
  var id = P.parametro('id');
  var original = id && D.productos.filter(function (p) { return p.id === id; })[0];
  if (original) {
    $('titulo-form').textContent = 'Editar producto';
    document.title = 'Editar ' + original.nombre + ' · Pahuichi POS';
    $('codigo').value = original.id;
    $('nombre').value = original.nombre;
    $('corto').value = original.corto;
    $('categoria').value = original.categoria;
    $('precio').value = P.bs(original.precio);
    $('unidad').value = original.unidad;
    $('favorito').checked = !!original.favorito;
    $('cocina').checked = !!original.cocina;
    $('agotado').checked = !!original.agotado;
    if (original.categoria === 'platos') modificadores = [{ nombre: 'Sin cebolla', precio: 0 }, { nombre: 'Porción extra de arroz', precio: 5 }];
    $('historial-precios').hidden = false;
    [['01/09/2026', 'Ana Lucía Vaca', original.precio - 5, original.precio], ['15/06/2026', 'Ana Lucía Vaca', original.precio - 10, original.precio - 5]].forEach(function (h) {
      var tr = $('filas-historial').insertRow();
      tr.innerHTML = '<td class="num"></td><td></td><td class="col-num num"></td><td class="col-num num"></td>';
      tr.cells[0].textContent = h[0];
      tr.cells[1].textContent = h[1];
      tr.cells[2].textContent = P.bs(Math.max(h[2], 1));
      tr.cells[3].textContent = P.bs(h[3]);
    });
  }

  /* Modificadores */
  function pintarModificadores() {
    var ul = $('modificadores');
    ul.innerHTML = '';
    modificadores.forEach(function (m, i) {
      var li = document.createElement('li');
      li.className = 'modificador';
      li.innerHTML = '<span class="modificador__nombre"></span><span class="modificador__precio num"></span><button class="boton boton--fantasma boton--icono" type="button"><svg class="icono" aria-hidden="true"><use href="#i-basura"></use></svg></button>';
      li.children[0].textContent = m.nombre;
      li.children[1].textContent = m.precio ? '+Bs ' + P.bs(m.precio) : 'Sin costo';
      li.children[2].setAttribute('aria-label', 'Quitar ' + m.nombre);
      li.children[2].addEventListener('click', function () { modificadores.splice(i, 1); pintarModificadores(); $('mod-nombre').focus(); });
      ul.appendChild(li);
    });
  }

  $('agregar-mod').addEventListener('click', function () {
    var nombre = $('mod-nombre').value.trim();
    var precio = $('mod-precio').value ? P.leerMonto($('mod-precio').value) : 0;
    P.marcarCampo('mod-nombre', nombre ? '' : 'Escribe el modificador.');
    if (!nombre) { $('mod-nombre').focus(); return; }
    modificadores.push({ nombre: nombre, precio: precio || 0 });
    $('mod-nombre').value = '';
    $('mod-precio').value = '';
    pintarModificadores();
    $('mod-nombre').focus();
  });

  /* Foto: recorte cuadrado y compresión a 200 KB o menos */
  function procesarFoto(file) {
    P.marcarCampo('foto', '');
    if (!file) return;
    if (!/^image\//.test(file.type)) {
      $('zona-foto').setAttribute('aria-invalid', 'true');
      P.marcarCampo('foto', '«' + file.name + '» no es una imagen. Elige una foto JPG, PNG o WebP.');
      return;
    }
    var img = new Image();
    img.onload = function () {
      var lado = Math.min(img.naturalWidth, img.naturalHeight);
      var lienzo = $('foto-lienzo');
      var ctx = lienzo.getContext('2d');
      ctx.drawImage(img, (img.naturalWidth - lado) / 2, (img.naturalHeight - lado) / 2, lado, lado, 0, 0, lienzo.width, lienzo.height);
      URL.revokeObjectURL(img.src);
      // Baja la calidad hasta que pese 200 KB o menos
      var calidad = 0.85;
      (function comprimir() {
        lienzo.toBlob(function (blob) {
          if (blob.size > MAX_BYTES && calidad > 0.4) { calidad -= 0.1; comprimir(); return; }
          $('foto-peso').textContent = 'Recortada a 480 × 480 · ' + Math.max(1, Math.round(blob.size / 1024)) + ' KB';
          $('foto-vista').hidden = false;
          $('foto-quitar').hidden = false;
          $('zona-foto').removeAttribute('aria-invalid');
        }, 'image/jpeg', calidad);
      })();
    };
    img.onerror = function () { P.marcarCampo('foto', 'No se pudo leer la imagen. Prueba con otra foto.'); };
    img.src = URL.createObjectURL(file);
  }

  $('foto').addEventListener('change', function () { procesarFoto(this.files[0]); });
  $('foto-camara').addEventListener('change', function () { procesarFoto(this.files[0]); });
  $('foto-quitar').addEventListener('click', function () {
    $('foto-vista').hidden = true;
    this.hidden = true;
    $('foto').value = '';
    $('foto-camara').value = '';
  });

  ['dragenter', 'dragover'].forEach(function (t) { $('zona-foto').addEventListener(t, function () { $('zona-foto').classList.add('es-arrastre'); }); });
  ['dragleave', 'drop'].forEach(function (t) { $('zona-foto').addEventListener(t, function () { $('zona-foto').classList.remove('es-arrastre'); }); });

  /* Validación y guardado */
  $('codigo').addEventListener('input', function () { this.value = this.value.toUpperCase(); });
  $('precio').addEventListener('blur', function () { var v = P.leerMonto(this.value); if (v !== null) this.value = P.bs(v); });

  var REGLAS = {
    codigo: function (v) {
      if (!v.trim()) return 'Escribe el código.';
      if (!/^[A-Z0-9-]{2,12}$/.test(v.trim())) return 'Usa letras, números y guion, sin espacios.';
      var repetido = D.productos.some(function (p) { return p.id === v.trim() && (!original || p.id !== original.id); });
      return repetido ? 'Ese código ya lo usa otro producto.' : '';
    },
    nombre: function (v) { return v.trim() ? '' : 'Escribe el nombre del producto.'; },
    corto: function (v) { return v.trim() ? '' : 'Escribe el nombre corto para el botón.'; },
    precio: function (v) {
      var n = P.leerMonto(v);
      if (!v.trim()) return 'Escribe el precio.';
      if (n === null) return 'Escribe un monto válido, por ejemplo 35,00.';
      return n > 0 ? '' : 'El precio debe ser mayor que cero.';
    }
  };

  $('form-producto').addEventListener('submit', function (e) {
    e.preventDefault();
    var errores = [];
    Object.keys(REGLAS).forEach(function (campo) {
      var mensaje = REGLAS[campo]($(campo).value);
      P.marcarCampo(campo, mensaje);
      if (mensaje) errores.push({ campo: campo, mensaje: mensaje });
    });
    var lista = $('lista-errores');
    lista.innerHTML = '';
    if (errores.length) {
      errores.forEach(function (err) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = '#' + err.campo;
        a.textContent = err.mensaje;
        a.addEventListener('click', function (ev) { ev.preventDefault(); $(err.campo).focus(); });
        li.appendChild(a);
        lista.appendChild(li);
      });
      $('resumen-errores').hidden = false;
      $('resumen-errores').focus();
      return;
    }
    $('resumen-errores').hidden = true;
    $('guardar').setAttribute('aria-busy', 'true');
    $('guardar').disabled = true;
    window.setTimeout(function () { window.location.href = 'productos.html?guardado=1'; }, 400);
  });

  pintarModificadores();
})();
