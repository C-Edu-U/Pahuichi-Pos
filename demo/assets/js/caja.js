/* Caja (demo). Reglas: una sola caja abierta (RN-01); el turno de noche no
   abre si el de medio día no está cerrado y arqueado (RN-02); cierre ciego:
   el conteo nunca muestra el esperado. Esperado = fondo + ventas en efectivo
   + ingresos − egresos. Observación obligatoria si |diferencia| >= 10 Bs. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var ESPERADO = 1660.5;
  var UMBRAL = 10;
  var CLAVE = 'pahuichi.turno';

  function abierta() { return P.leer(CLAVE) !== 'cerrado'; }

  function vista(nombre) {
    ['abrir', 'abierta', 'conteo', 'resumen'].forEach(function (v) {
      $('vista-' + v).hidden = v !== nombre;
    });
    $('seccion-historial').hidden = nombre === 'conteo' || nombre === 'resumen';
  }

  function inicio() {
    vista(abierta() ? 'abierta' : 'abrir');
    $('aviso-sin-qr').hidden = P.leer('pahuichi.qrVencido') !== '1';
    // El cajero abre su propia caja
    if (P.rolActual() === 'cajero') {
      $('abrir-cajero').value = 'Mariela Suárez Añez';
      $('abrir-cajero').disabled = true;
    }
  }

  /* Abrir caja */
  $('form-abrir').addEventListener('submit', function (e) {
    e.preventDefault();
    var fondo = P.leerMonto($('fondo').value);
    if (fondo === null) {
      P.marcarCampo('fondo', 'Escribe el fondo inicial, por ejemplo 300,00.');
      $('fondo').focus();
      return;
    }
    P.marcarCampo('fondo', '');
    if (abierta()) {
      // RN-01 y RN-02: no se abre otra caja mientras haya una abierta
      $('abrir-bloqueo-titulo').textContent = 'No se puede abrir la caja del turno de ' + ($('abrir-turno').value === 'noche' ? 'noche' : 'medio día');
      $('abrir-bloqueo-texto').textContent = 'La caja del turno de medio día (Mariela Suárez) sigue abierta. Primero se debe cerrar y arquear; solo puede haber una caja abierta.';
      $('abrir-bloqueo').hidden = false;
      $('abrir-bloqueo').focus();
      return;
    }
    P.guardar(CLAVE, 'abierto');
    inicio();
    P.aviso('Caja abierta con un fondo de Bs ' + P.bs(fondo) + '.');
  });

  // «Abrir otra caja» mientras hay una abierta: muestra la regla
  $('intentar-abrir').addEventListener('click', function () {
    vista('abrir');
    $('abrir-turno').value = 'noche';
    $('abrir-cajero').value = 'Jorge Rivero Chávez';
    $('abrir-bloqueo').hidden = true;
    $('fondo').focus();
  });

  /* Movimientos de efectivo */
  $('form-movimiento').addEventListener('submit', function (e) {
    e.preventDefault();
    var monto = P.leerMonto($('mov-monto').value);
    var motivo = $('mov-motivo').value.trim();
    P.marcarCampo('mov-monto', monto && monto > 0 ? '' : 'Escribe un monto mayor que cero.');
    P.marcarCampo('mov-motivo', motivo ? '' : 'Escribe el motivo del movimiento.');
    if (!(monto > 0)) { $('mov-monto').focus(); return; }
    if (!motivo) { $('mov-motivo').focus(); return; }
    var tipo = document.querySelector('[name="tipo-mov"]:checked').value;
    var li = document.createElement('li');
    li.className = 'movimiento movimiento--' + tipo + ' fila-nueva';
    li.innerHTML = '<svg class="icono" aria-hidden="true"><use href="#i-' + tipo + '"></use></svg><div><p class="movimiento__motivo"></p><p class="movimiento__detalle"></p></div><span class="movimiento__monto num"></span>';
    li.querySelector('.movimiento__motivo').textContent = motivo;
    li.querySelector('.movimiento__detalle').textContent = '12:41 · ' + P.ROLES[P.rolActual()].usuario;
    li.querySelector('.movimiento__monto').textContent = (tipo === 'ingreso' ? '+' : '−') + P.bs(monto);
    $('lista-movimientos').appendChild(li);
    this.reset();
    P.aviso((tipo === 'ingreso' ? 'Ingreso' : 'Egreso') + ' de Bs ' + P.bs(monto) + ' registrado.');
  });

  /* Cierre ciego: conteo */
  function contado() {
    var total = 0;
    document.querySelectorAll('.control--conteo').forEach(function (input) {
      var n = parseInt(input.value.replace(/\D/g, ''), 10) || 0;
      var sub = Math.round(n * parseFloat(input.getAttribute('data-valor')) * 100) / 100;
      total += sub;
      input.nextElementSibling.textContent = P.bs(sub);
    });
    total = Math.round(total * 100) / 100;
    $('contado').innerHTML = '<span class="cifra__moneda">Bs</span>' + P.bs(total);
    return total;
  }

  document.querySelectorAll('.control--conteo').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '');
      contado();
    });
  });

  $('iniciar-cierre').addEventListener('click', function () {
    vista('conteo');
    $('d-200').focus();
  });

  $('cancelar-cierre').addEventListener('click', function () { vista('abierta'); });

  $('vista-conteo').addEventListener('submit', function (e) {
    e.preventDefault();
    var total = contado();
    var dif = Math.round((total - ESPERADO) * 100) / 100;
    $('r-contado').textContent = P.bs(total);
    $('r-diferencia').textContent = (dif > 0 ? '+' : dif < 0 ? '−' : '') + P.bs(Math.abs(dif));
    var estadoDif = dif === 0 ? '<span class="insignia insignia--ok">Cuadra</span>'
      : dif > 0 ? '<span class="insignia insignia--aviso">Sobran ' + P.bs(dif) + '</span>'
      : '<span class="insignia insignia--peligro">Faltan ' + P.bs(-dif) + '</span>';
    $('r-diferencia-estado').innerHTML = estadoDif;
    $('arqueo-final').classList.toggle('arqueo-final--diferencia', Math.abs(dif) >= UMBRAL);
    var obligatoria = Math.abs(dif) >= UMBRAL;
    $('observacion-opcional').textContent = obligatoria ? '(obligatoria)' : '(opcional)';
    $('observacion').required = obligatoria;
    P.marcarCampo('observacion', '');
    $('form-cerrar').hidden = false;
    $('cierre-hecho').hidden = true;
    vista('resumen');
    $('t-resumen').focus();
  });

  $('volver-contar').addEventListener('click', function () { vista('conteo'); });

  /* Cerrar caja */
  $('form-cerrar').addEventListener('submit', function (e) {
    e.preventDefault();
    var obs = $('observacion').value.trim();
    if ($('observacion').required && !obs) {
      P.marcarCampo('observacion', 'La diferencia es de Bs 10 o más: explica el motivo antes de cerrar.');
      $('observacion').focus();
      return;
    }
    if (obs) {
      $('r-observacion').hidden = false;
      $('r-observacion').textContent = 'Observación: ' + obs;
    }
    P.guardar(CLAVE, 'cerrado');
    this.hidden = true;
    $('cierre-hecho').hidden = false;
    P.aviso('Caja cerrada. El reporte de cierre se envió a la impresora.');
  });

  $('imprimir-reporte').addEventListener('click', function () {
    document.body.classList.add('imprime-reporte');
    window.print();
    document.body.classList.remove('imprime-reporte');
  });

  $('ir-abrir').addEventListener('click', inicio);

  /* Reabrir caja (administrador, con motivo) */
  var modalReabrir = $('modal-reabrir');
  modalReabrir.addEventListener('pahuichi:abrir', function () {
    $('reabrir-motivo').value = '';
    P.marcarCampo('reabrir-motivo', '');
  });
  $('confirmar-reabrir').addEventListener('click', function () {
    if (!$('reabrir-motivo').value.trim()) {
      P.marcarCampo('reabrir-motivo', 'Escribe el motivo: queda en la bitácora.');
      $('reabrir-motivo').focus();
      return;
    }
    if (abierta()) {
      P.marcarCampo('reabrir-motivo', 'Hay una caja abierta. Ciérrala antes de reabrir otra.');
      return;
    }
    modalReabrir.close();
    P.guardar(CLAVE, 'abierto');
    inicio();
    P.aviso('Caja reabierta. El motivo quedó en la bitácora.');
  });

  inicio();
})();
