/* Pagos QR (prototipo). En PHP: un solo QR activo, SHA-256 contra los
   anteriores, vence_en = subido_en + 24 h y un middleware lo marca vencido
   en cada petición (sin cron). Aquí el estado vive en localStorage para que
   el POS lo lea. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var CLAVE = 'pahuichi.qrVencido';
  var anteriores = ['qr-cobro-demo.svg', 'qr-bnb-0410.png', 'qr-union-0310.png'];

  function vencido() {
    try { return window.localStorage.getItem(CLAVE) === '1'; } catch (e) { return false; }
  }

  function fijar(v) {
    try { window.localStorage.setItem(CLAVE, v ? '1' : '0'); } catch (e) { /* sin almacenamiento */ }
    pintar();
  }

  function pintar() {
    var v = vencido();
    $('qr-por-vencer').hidden = v;
    $('qr-vencido').hidden = !v;
    $('qr-estado').className = 'insignia ' + (v ? 'insignia--peligro' : 'insignia--ok');
    $('qr-estado').textContent = v ? 'Vencido' : 'Activo';
    $('qr-vence').textContent = v ? 'Venció hoy a las 16:40' : 'Hoy, 16:40 (dura 24 horas)';
    $('qr-img').classList.toggle('qr-actual__img--vencido', v);
    $('btn-retirar').hidden = v;
    $('simular').textContent = v ? 'Volver a un QR vigente' : 'Simular vencimiento';
    var fila = $('filas-qr').rows[0].querySelector('.celda-estado');
    fila.innerHTML = v ? '<span class="insignia insignia--neutra">Vencido</span>' : '<span class="insignia insignia--ok">Activo</span>';
  }

  $('simular').addEventListener('click', function () { fijar(!vencido()); });

  $('modal-retirar').addEventListener('close', function () {
    if (this.returnValue !== 'retirar') return;
    fijar(true);
    $('qr-estado').textContent = 'Retirado';
    $('filas-qr').rows[0].querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--peligro">Retirado</span>';
    P.aviso('QR retirado. El cobro con QR queda bloqueado hasta subir uno nuevo.');
  });

  // Archivo: tipo real, tamaño y que no se repita un QR anterior
  var archivoValido = null;
  $('qr-archivo').addEventListener('change', function () {
    var f = this.files && this.files[0];
    archivoValido = null;
    $('qr-archivo-nombre').hidden = true;
    if (!f) return;
    var error = ['image/png', 'image/jpeg', 'image/webp'].indexOf(f.type) === -1 ? '«' + f.name + '» no es una imagen PNG, JPG o WebP.'
      : f.size > 2 * 1024 * 1024 ? 'La imagen pasa de 2 MB.'
      : anteriores.indexOf(f.name) !== -1 ? 'Esta imagen ya se usó como QR. Genera un QR nuevo en tu banco.' : '';
    $('zona-qr').toggleAttribute('aria-invalid', !!error);
    P.marcarCampo('qr-archivo', error);
    if (error) { this.value = ''; return; }
    archivoValido = f;
    $('qr-archivo-nombre').hidden = false;
    $('qr-archivo-nombre').textContent = 'Elegido: ' + f.name;
  });

  $('form-qr').addEventListener('submit', function (e) {
    e.preventDefault();
    var monto = P.leerMonto($('qr-monto').value);
    P.marcarCampo('qr-archivo', archivoValido ? '' : 'Elige la imagen del QR nuevo.');
    P.marcarCampo('qr-monto', monto && monto > 0 ? '' : 'Escribe el monto fijo del QR, mayor que cero.');
    if (!archivoValido) { $('qr-archivo').focus(); return; }
    if (!(monto > 0)) { $('qr-monto').focus(); return; }

    var tr = document.createElement('tr');
    tr.className = 'fila-nueva';
    tr.innerHTML = '<td class="num">06/10/2026 14:35</td><td>Ana Lucía Vaca</td><td></td><td class="col-num num"></td><td>En curso</td><td class="col-num num">0</td><td class="celda-estado"><span class="insignia insignia--ok">Activo</span></td>';
    tr.cells[2].textContent = $('qr-banco').value || 'Sin banco';
    tr.cells[3].textContent = P.bs(monto);
    var anterior = $('filas-qr').rows[0];
    anterior.querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--neutra">Reemplazado</span>';
    anterior.querySelector('.celda-estado').classList.remove('celda-estado');
    anterior.cells[4].textContent = '21 h 55 min';
    $('filas-qr').insertBefore(tr, anterior);
    anteriores.push(archivoValido.name);

    fijar(false);
    $('qr-vence').textContent = 'Mañana, 14:35 (dura 24 horas)';
    $('qr-por-vencer').hidden = true;
    this.hidden = true;
    this.reset();
    archivoValido = null;
    $('qr-archivo-nombre').hidden = true;
    P.aviso('QR nuevo activo hasta mañana a las 14:35. El anterior quedó como reemplazado.');
  });

  pintar();
})();
