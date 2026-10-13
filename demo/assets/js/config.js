/* Configuración (demo): guardado por sección, leyendas, impresoras y respaldo. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var LEYENDAS = [
    'El proveedor deberá entregar el producto en las modalidades y términos ofertados o convenidos.',
    'Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.',
    'Está prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.',
    'Tienes derecho a un trato equitativo sin discriminación en la oferta de productos.',
    'El proveedor debe exhibir certificaciones de habilitación o documentos que acrediten la calidad de sus productos.',
    'Tienes derecho a la reposición o devolución del producto si no cumple lo ofertado.',
    'El proveedor debe brindar atención sin discriminación, con trato cordial y oportuno.',
    'Tienes derecho a denunciar las infracciones a tus derechos como consumidor.'
  ];

  function pintarLeyendas() {
    var ul = $('lista-leyendas');
    ul.innerHTML = '';
    LEYENDAS.forEach(function (t, i) {
      var li = document.createElement('li');
      li.className = 'leyenda-fila';
      li.innerHTML = '<span class="num leyenda-fila__n"></span><p></p><button class="interruptor-boton" type="button" role="switch" aria-checked="true"></button>';
      li.children[0].textContent = i + 1;
      li.children[1].textContent = 'Ley N° 453: ' + t;
      li.children[2].setAttribute('aria-label', 'Leyenda ' + (i + 1) + ' activa');
      ul.appendChild(li);
    });
  }

  $('lista-leyendas').addEventListener('click', function (e) {
    var s = e.target.closest('[role="switch"]');
    if (!s) return;
    var activas = document.querySelectorAll('#lista-leyendas [aria-checked="true"]').length;
    var on = s.getAttribute('aria-checked') !== 'true';
    if (!on && activas <= 8) { P.aviso('Deben quedar al menos 8 leyendas activas.'); return; }
    s.setAttribute('aria-checked', String(on));
  });

  $('form-leyenda').addEventListener('submit', function (e) {
    e.preventDefault();
    var texto = $('l-texto').value.trim();
    P.marcarCampo('l-texto', texto ? '' : 'Escribe el texto de la leyenda.');
    if (!texto) { $('l-texto').focus(); return; }
    LEYENDAS.push(texto.replace(/^Ley N° 453:\s*/i, ''));
    pintarLeyendas();
    this.reset();
    P.aviso('Leyenda agregada.');
  });

  document.querySelectorAll('form[data-guardar]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.id === 'negocio') {
        var errores = {
          'n-nit': /^\d{7,13}$/.test($('n-nit').value.trim()) ? '' : 'El NIT tiene solo números, entre 7 y 13 dígitos.',
          'n-razon': $('n-razon').value.trim() ? '' : 'Escribe la razón social.'
        };
        var primero = null;
        Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
        if (primero) { $(primero).focus(); return; }
      }
      P.aviso(form.getAttribute('data-guardar'));
    });
  });

  document.querySelectorAll('[data-prueba]').forEach(function (b) {
    b.addEventListener('click', function () {
      P.aviso('Página de prueba enviada a la impresora de ' + b.closest('.impresora').querySelector('.impresora__nombre').textContent.toLowerCase() + '.');
    });
  });

  $('respaldar').addEventListener('click', function () {
    var b = this;
    b.setAttribute('aria-busy', 'true');
    b.disabled = true;
    window.setTimeout(function () {
      b.removeAttribute('aria-busy');
      b.disabled = false;
      $('ultimo-respaldo').textContent = 'Hoy, 12:41 · 2,4 MB';
      P.aviso('Copia de seguridad creada.');
    }, 900);
  });

  pintarLeyendas();
})();
