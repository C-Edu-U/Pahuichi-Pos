/* QR del día (demo). Con servidor: fecha única por QR, vigente hasta las
   23:59:59, un middleware lo marca vencido en cada petición (sin cron) y la
   imagen se compara por SHA-256 con las anteriores (desactivable). Aquí el
   estado vive en localStorage para que el POS y el menú lo lean. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var CLAVE = 'pahuichi.qrVencido';
  var HOY = '2026-10-08';

  function sinQr() { return P.leer(CLAVE) === '1'; }

  function fijar(sin, estadoFila) {
    P.guardar(CLAVE, sin ? '1' : '0');
    pintar(estadoFila);
  }

  function pintar(estadoFila) {
    var sin = sinQr();
    $('qr-sin').hidden = !sin;
    $('qr-vigente').hidden = sin;
    $('simular').textContent = sin ? 'Volver al QR de hoy' : 'Simular medianoche';
    var celda = $('filas-qr').rows[0].querySelector('.celda-estado');
    if (celda) {
      celda.innerHTML = !sin ? '<span class="insignia insignia--ok">Vigente</span>'
        : estadoFila === 'retirado' ? '<span class="insignia insignia--peligro">Retirado</span>'
        : '<span class="insignia insignia--neutra">Vencido</span>';
    }
    var aviso = document.querySelector('.menu__item[data-modulo="qr"] .menu__aviso');
    if (aviso) aviso.hidden = !sin;
    // Con QR vigente, el formulario sirve para reemplazarlo
    $('t-subir').textContent = sin ? 'Subir el QR de hoy' : 'Reemplazar el QR de hoy';
  }

  // Demo: el reloj llega a medianoche y el QR vence solo
  $('simular').addEventListener('click', function () {
    var sin = !sinQr();
    fijar(sin, 'vencido');
    P.aviso(sin ? 'Medianoche: el QR del día venció. La caja ya no puede cobrar con QR.' : 'El QR de hoy vuelve a estar vigente.');
  });

  $('modal-retirar').addEventListener('close', function () {
    if (this.returnValue !== 'retirar') return;
    fijar(true, 'retirado');
    P.aviso('QR retirado. La caja no puede cobrar con QR hasta que subas otro.');
  });

  /* Subir: tipo, tamaño, fecha de hoy e imagen nueva (SHA-256) */
  var archivo = null;
  var huella = null;

  function hashes() {
    try { return JSON.parse(P.leer('pahuichi.qrHashes') || '[]'); } catch (e) { return []; }
  }

  function sha256(file) {
    if (!(window.crypto && crypto.subtle)) return Promise.resolve(null);
    return file.arrayBuffer().then(function (buf) { return crypto.subtle.digest('SHA-256', buf); }).then(function (h) {
      return Array.prototype.map.call(new Uint8Array(h), function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    });
  }

  $('qr-archivo').addEventListener('change', function () {
    var f = this.files && this.files[0];
    var input = this;
    archivo = null;
    huella = null;
    $('qr-archivo-nombre').hidden = true;
    if (!f) return;
    var error = ['image/png', 'image/jpeg', 'image/webp'].indexOf(f.type) === -1 ? '«' + f.name + '» no es una imagen PNG, JPG o WebP.'
      : f.size > 2 * 1024 * 1024 ? 'La imagen pasa de 2 MB.' : '';
    if (error) {
      $('zona-qr').setAttribute('aria-invalid', 'true');
      P.marcarCampo('qr-archivo', error);
      input.value = '';
      return;
    }
    sha256(f).then(function (h) {
      if (h && hashes().indexOf(h) !== -1) {
        $('zona-qr').setAttribute('aria-invalid', 'true');
        P.marcarCampo('qr-archivo', 'Esta imagen es idéntica a un QR anterior. Genera el QR de hoy en la app del banco.');
        input.value = '';
        return;
      }
      $('zona-qr').removeAttribute('aria-invalid');
      P.marcarCampo('qr-archivo', '');
      archivo = f;
      huella = h;
      $('qr-archivo-nombre').hidden = false;
      $('qr-archivo-nombre').textContent = 'Elegida: ' + f.name;
    });
  });

  $('form-qr').addEventListener('submit', function (e) {
    e.preventDefault();
    var monto = P.leerMonto($('qr-monto').value);
    var errores = {
      'qr-archivo': archivo ? '' : 'Elige la imagen del QR de hoy.',
      'qr-fecha': $('qr-fecha').value === HOY ? '' : 'El QR es de otro día. Solo se acepta el QR con fecha de hoy (08/10/2026).',
      'qr-monto': monto && monto > 0 ? '' : 'Escribe el monto fijo, mayor que cero.'
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
    if (primero) { $(primero).focus(); return; }

    if (huella) P.guardar('pahuichi.qrHashes', JSON.stringify(hashes().concat(huella)));
    $('qr-img').src = URL.createObjectURL(archivo);
    $('filas-qr').rows[0].cells[0].textContent = '08/10/2026 · 12:41';
    this.reset();
    $('qr-fecha').value = HOY;
    archivo = null;
    $('qr-archivo-nombre').hidden = true;
    fijar(false);
    P.aviso('QR de hoy activo hasta las 23:59:59. La caja ya puede cobrar con QR.');
  });

  pintar(P.leer('pahuichi.qrEstado'));
})();
