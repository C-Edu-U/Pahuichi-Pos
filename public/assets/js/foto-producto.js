/* HU-15: selección, arrastre y cámara. La foto enviada es el JPEG de la vista previa. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var input = $('foto');
  if (!input) return;
  var form = $('form-producto'), canvas = $('foto-lienzo'), zona = $('zona-foto');
  var stream = null, apertura = 0, proceso = 0, ocupado = false, invalido = !$('foto-error').hidden;
  var tipos = ['image/jpeg', 'image/png', 'image/webp'];
  function error(texto) {
    $('foto-error').textContent = texto; $('foto-error').hidden = !texto;
    zona.setAttribute('aria-invalid', texto ? 'true' : 'false'); invalido = Boolean(texto);
  }
  function cerrarCamara() {
    apertura++;
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null; $('camara-video').srcObject = null;
    $('camara-panel').hidden = true; $('camara-capturar').disabled = true;
    $('foto-camara').disabled = false;
  }
  async function procesar(file) {
    if (!file) return;
    var turno = ++proceso; ocupado = true;
    input.value = ''; error('');
    try {
      if (tipos.indexOf(file.type) === -1 || !/\.(jpe?g|png|webp)$/i.test(file.name)) throw new Error('Elige una imagen JPG, PNG o WebP.');
      if (file.size > 10 * 1024 * 1024) throw new Error('La foto supera el límite de 10 MB.');
      var url = URL.createObjectURL(file), img = new Image();
      try {
        img.src = url; await img.decode();
        if (img.naturalWidth * img.naturalHeight > 24000000) throw new Error('La foto debe tener como máximo 24 megapíxeles.');
        if (turno !== proceso) return;
        var lado = Math.min(img.naturalWidth, img.naturalHeight), ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 480, 480);
        ctx.drawImage(img, (img.naturalWidth - lado) / 2, (img.naturalHeight - lado) / 2, lado, lado, 0, 0, 480, 480);
      } finally { URL.revokeObjectURL(url); }
      var blob;
      for (var calidad = 0.85; calidad >= 0.24; calidad -= 0.1) {
        blob = await new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', calidad); });
        if (blob && blob.size <= 200 * 1024) break;
      }
      if (turno !== proceso) return;
      if (!blob || blob.size > 200 * 1024) throw new Error('No se pudo comprimir la foto a 200 KB. Elige otra imagen.');
      var transferencia = new DataTransfer();
      transferencia.items.add(new File([blob], 'producto.jpg', {type: 'image/jpeg'}));
      input.files = transferencia.files;
      $('foto-quitar-valor').value = '0'; $('foto-vista').hidden = false; $('foto-quitar').hidden = false;
      $('foto-peso').textContent = 'Recortada a 480 × 480 · ' + Math.ceil(blob.size / 1024) + ' KB · lista para guardar';
      error('');
    } catch (e) {
      if (turno === proceso) {
        error(e instanceof Error && !/source image|decode|encoding/i.test(e.message) ? e.message : 'No se pudo leer la imagen. Elige otra foto.');
        $('foto-quitar').hidden = false;
      }
    } finally { if (turno === proceso) ocupado = false; }
  }
  input.addEventListener('change', function () { procesar(this.files[0]); });
  $('camara-nativa').addEventListener('change', function () { procesar(this.files[0]); this.value = ''; });
  ['dragenter', 'dragover'].forEach(function (evento) {
    zona.addEventListener(evento, function (e) { e.preventDefault(); zona.classList.add('es-arrastre'); });
  });
  zona.addEventListener('dragleave', function () { zona.classList.remove('es-arrastre'); });
  zona.addEventListener('drop', function (e) {
    e.preventDefault(); e.stopPropagation(); zona.classList.remove('es-arrastre');
    if (e.dataTransfer.files.length !== 1) {
      proceso++; ocupado = false; input.value = ''; error('Arrastra una sola imagen JPG, PNG o WebP.'); $('foto-quitar').hidden = false; return;
    }
    procesar(e.dataTransfer.files[0]);
  });
  // Un archivo soltado fuera de la zona tampoco debe abrirse como otra página.
  document.addEventListener('dragover', function (e) { e.preventDefault(); });
  document.addEventListener('drop', function (e) { e.preventDefault(); });
  $('foto-quitar').addEventListener('click', function () {
    proceso++; ocupado = false; input.value = ''; $('camara-nativa').value = '';
    $('foto-quitar-valor').value = '1'; $('foto-vista').hidden = true; this.hidden = true; error(''); cerrarCamara();
  });
  $('foto-camara').addEventListener('click', async function () {
    error('');
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      error('Para la cámara en vivo abre el sistema mediante HTTPS y permite el acceso. También puedes elegir una foto.');
      // Esto es un error de cámara, no un archivo inválido: permite guardar los demás datos.
      invalido = false; $('camara-nativa-etiqueta').hidden = false; return;
    }
    cerrarCamara(); var intento = ++apertura;
    $('foto-camara').disabled = true; $('camara-panel').hidden = false;
    try {
      var recibido = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
      if (intento !== apertura) { recibido.getTracks().forEach(function (t) { t.stop(); }); return; }
      stream = recibido; $('camara-video').srcObject = stream;
      await $('camara-video').play();
      if (intento === apertura) $('camara-capturar').disabled = false;
    } catch (e) {
      if (intento !== apertura) return;
      cerrarCamara();
      error(e.name === 'NotAllowedError' ? 'Permite el acceso a la cámara en el navegador y vuelve a intentarlo.' : 'No se pudo abrir la cámara. Comprueba que esté conectada y libre, o elige una foto.');
      invalido = false; $('camara-nativa-etiqueta').hidden = false;
    }
  });
  $('camara-cerrar').addEventListener('click', cerrarCamara);
  $('camara-capturar').addEventListener('click', function () {
    var video = $('camara-video');
    if (!video.videoWidth || !video.videoHeight) return;
    var temporal = document.createElement('canvas'), lado = Math.min(video.videoWidth, video.videoHeight);
    temporal.width = temporal.height = 480;
    temporal.getContext('2d').drawImage(video, (video.videoWidth - lado) / 2, (video.videoHeight - lado) / 2, lado, lado, 0, 0, 480, 480);
    temporal.toBlob(function (blob) { if (blob) procesar(new File([blob], 'camara.jpg', {type: 'image/jpeg'})); }, 'image/jpeg', 0.9);
    cerrarCamara();
  });
  window.addEventListener('pagehide', cerrarCamara);
  document.addEventListener('visibilitychange', function () { if (document.hidden) cerrarCamara(); });
  form.addEventListener('submit', function (e) {
    if (ocupado || invalido) {
      e.preventDefault(); e.stopImmediatePropagation();
      if (ocupado) { $('foto-error').hidden = false; $('foto-error').textContent = 'Espera a que termine de preparar la foto.'; }
      input.focus(); $('guardar').removeAttribute('aria-busy');
    } else cerrarCamara();
  }, true);
  if (canvas.dataset.actual) {
    var actual = new Image(); actual.onload = function () {
      if (proceso === 0) canvas.getContext('2d').drawImage(actual, 0, 0, 480, 480);
    }; actual.src = canvas.dataset.actual;
  }
})();
