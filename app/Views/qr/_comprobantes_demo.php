<section class="panel-bloque" aria-labelledby="qr-duplicado-titulo">
 <div class="panel-bloque__cabecera"><h2 class="panel-bloque__titulo" id="qr-duplicado-titulo">Demostración de comprobantes · HU-40</h2></div>
 <div class="panel-bloque__cuerpo">
  <p>Escribe el comprobante de una venta QR ya registrada. Ambos escenarios deben rechazar su reutilización: el mismo día y al simular el día siguiente.</p>
  <p class="campo__ayuda">Solo prueba la validación del comprobante. No cambia fechas, QR ni ventas, no registra pagos y no modifica el reloj del sistema.</p>
  <div class="campo"><label class="campo__etiqueta" for="qr-demo-comprobante">Comprobante ya registrado</label><input class="control" id="qr-demo-comprobante" maxlength="80" autocomplete="off" placeholder="Ej.: 000555"></div>
  <div class="qr-demo__acciones">
   <button class="boton" type="button" id="qr-demo-mismo-dia">Simular mismo día</button>
   <button class="boton" type="button" id="qr-demo-otro-dia">Simular otro día</button>
  </div>
  <div id="qr-demo-comprobante-resultado" role="status" aria-live="polite" hidden></div>
 </div>
</section>
