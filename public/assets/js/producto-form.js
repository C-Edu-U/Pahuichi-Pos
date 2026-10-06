/* Formulario de producto (prototipo). La validación real ocurre en el
   servidor: finfo para el tipo, 2 MB, nombre aleatorio y CSRF. Aquí se
   adelanta en el cliente para dar el error junto al campo. */
(function () {
  'use strict';

  var MAX_BYTES = 2 * 1024 * 1024;
  var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

  // Semillas del HANDOFF (sección 5) para el modo edición
  var PRODUCTOS = {
    'PL-01': ['Majadito de charque', 1, '30,00', '561011', '63111', '58'],
    'PL-02': ['Locro carretero', 1, '28,00', '561011', '63111', '58'],
    'PL-03': ['Keperí al horno', 1, '45,00', '561011', '63111', '58'],
    'PL-04': ['Pacumutu', 1, '50,00', '561011', '63111', '58'],
    'PL-05': ['Arroz con queso', 1, '20,00', '561011', '63111', '58'],
    'HO-01': ['Cuñapé', 2, '3,00', '107100', '10712', '57'],
    'HO-02': ['Pan de arroz', 2, '4,00', '107100', '10712', '57'],
    'HO-03': ['Empanada de arroz', 2, '5,00', '107100', '10712', '57'],
    'HO-04': ['Humintas al horno', 2, '6,00', '107100', '10712', '57'],
    'HO-05': ['Sonso de yuca', 2, '10,00', '107100', '', '57'],
    'HO-06': ['Masaco de plátano', 2, '15,00', '107100', '10712', '57'],
    'BE-01': ['Somó', 3, '8,00', '110400', '11041', '57'],
    'BE-02': ['Chicha cruceña', 3, '8,00', '110400', '11041', '57'],
    'BE-03': ['Mocochinchi', 3, '7,00', '110400', '11041', '57'],
    'PR-01': ['Producto de prueba QR', 5, '0,50', '561011', '99100', '58']
  };

  var form = document.getElementById('form-producto');
  var $ = function (id) { return document.getElementById(id); };

  /* Modo edición */
  var id = window.Pahuichi.parametro('id');
  if (id && PRODUCTOS[id]) {
    var p = PRODUCTOS[id];
    $('titulo-form').textContent = 'Editar producto';
    document.title = 'Editar ' + p[0] + ' · Pahuichi POS';
    $('nombre').value = p[0];
    $('codigo').value = id;
    $('categoria').value = String(p[1]);
    $('precio').value = p[2];
    $('actividad').value = p[3];
    $('codigo-sin').value = p[4];
    $('unidad').value = p[5];
    if (id === 'HO-06') $('disponible').checked = false;
  }

  /* Errores junto al campo */
  function marcar(campo, mensaje) {
    var control = $(campo);
    var error = $(campo + '-error');
    if (mensaje) {
      error.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-error"></use></svg><span></span>';
      error.querySelector('span').textContent = mensaje;
      error.hidden = false;
      if (control) control.setAttribute('aria-invalid', 'true');
    } else {
      error.hidden = true;
      error.textContent = '';
      if (control) control.removeAttribute('aria-invalid');
    }
  }

  /* Precio: coma decimal, dos decimales, mayor que cero */
  function leerPrecio(texto) {
    var limpio = texto.trim().replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
    if (!/^\d+(\.\d{1,2})?$/.test(limpio)) return null;
    return Math.round(parseFloat(limpio) * 100) / 100;
  }

  function formatearPrecio(valor) {
    return valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  $('precio').addEventListener('blur', function () {
    var v = leerPrecio(this.value);
    if (v !== null) this.value = formatearPrecio(v);
  });

  $('codigo').addEventListener('input', function () {
    var pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
  });

  $('codigo-sin').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
  });

  var REGLAS = {
    nombre: function (v) { return v.trim() ? '' : 'Escribe el nombre del producto.'; },
    codigo: function (v) {
      if (!v.trim()) return 'Escribe el código interno.';
      return /^[A-Z0-9-]{2,12}$/.test(v.trim()) ? '' : 'Usa letras, números y guion, sin espacios.';
    },
    categoria: function (v) { return v ? '' : 'Elige una categoría.'; },
    precio: function (v) {
      if (!v.trim()) return 'Escribe el precio.';
      var n = leerPrecio(v);
      if (n === null) return 'Escribe un monto válido, por ejemplo 28,50.';
      return n > 0 ? '' : 'El precio debe ser mayor que cero: no se emiten facturas en cero.';
    },
    actividad: function (v) { return v ? '' : 'Elige la actividad económica.'; },
    'codigo-sin': function (v) { return v.trim() ? '' : 'Falta el código SIN: sin él el producto no queda homologado.'; },
    unidad: function (v) { return v ? '' : 'Elige la unidad de medida.'; }
  };

  // Al corregir un campo marcado, el error se va en cuanto queda bien
  Object.keys(REGLAS).forEach(function (campo) {
    var el = $(campo);
    el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', function () {
      if (el.getAttribute('aria-invalid') === 'true') marcar(campo, REGLAS[campo](el.value));
    });
  });

  /* Foto: tipo y tamaño */
  var zona = $('zona-foto');
  var entrada = $('foto');
  var elegida = $('foto-elegida');
  var urlVista = null;

  function mb(bytes) {
    return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
  }

  function quitarFoto() {
    entrada.value = '';
    elegida.hidden = true;
    var img = elegida.querySelector('img');
    if (img) img.remove();
    if (urlVista) { URL.revokeObjectURL(urlVista); urlVista = null; }
    zona.hidden = false;
  }

  function validarFoto(archivo) {
    if (TIPOS.indexOf(archivo.type) === -1) {
      return '«' + archivo.name + '» no es una imagen JPG, PNG o WebP.';
    }
    if (archivo.size > MAX_BYTES) {
      return 'La foto pesa ' + mb(archivo.size) + '. El máximo es 2 MB.';
    }
    return '';
  }

  entrada.addEventListener('change', function () {
    var archivo = entrada.files && entrada.files[0];
    if (!archivo) return;
    var error = validarFoto(archivo);
    if (error) {
      entrada.value = '';
      zona.setAttribute('aria-invalid', 'true');
      marcar('foto', error);
      return;
    }
    zona.removeAttribute('aria-invalid');
    marcar('foto', '');
    urlVista = URL.createObjectURL(archivo);
    var img = document.createElement('img');
    img.className = 'archivo__vista';
    img.alt = 'Vista previa de la foto elegida';
    img.src = urlVista;
    elegida.insertBefore(img, elegida.firstChild);
    $('foto-nombre').textContent = archivo.name + ' · ' + mb(archivo.size);
    elegida.hidden = false;
    zona.hidden = true;
  });

  $('foto-quitar').addEventListener('click', function () {
    quitarFoto();
    entrada.focus();
  });

  ['dragenter', 'dragover'].forEach(function (t) {
    zona.addEventListener(t, function () { zona.classList.add('es-arrastre'); });
  });
  ['dragleave', 'drop'].forEach(function (t) {
    zona.addEventListener(t, function () { zona.classList.remove('es-arrastre'); });
  });

  /* Envío */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var errores = [];
    Object.keys(REGLAS).forEach(function (campo) {
      var mensaje = REGLAS[campo]($(campo).value);
      marcar(campo, mensaje);
      if (mensaje) errores.push({ campo: campo, mensaje: mensaje });
    });

    var resumen = $('resumen-errores');
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
      resumen.hidden = false;
      resumen.focus();
      return;
    }

    resumen.hidden = true;
    var boton = $('guardar');
    boton.setAttribute('aria-busy', 'true');
    boton.disabled = true;
    window.setTimeout(function () { window.location.href = 'productos.html?guardado=1'; }, 500);
  });
})();
