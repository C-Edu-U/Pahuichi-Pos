<?php
/**
 * Mesa del pedido (HU-35): las mesas 1 a 12 en botones y otra escrita (1 a 99). El campo
 * es el valor; los botones lo llenan (app.js). Variables: $idMesa, $idMesas, $describeMesa.
 */
?>
            <div class="mesas mesas--pedido" id="<?= e($idMesas) ?>" role="group" aria-label="Mesa">
<?php for ($n = 1; $n <= 12; $n++): ?>
              <button class="mesa-opcion" type="button" data-mesa="<?= $n ?>" aria-pressed="false"><?= $n ?></button>
<?php endfor; ?>
              <label class="visualmente-oculto" for="<?= e($idMesa) ?>">Otra mesa, de 1 a 99</label>
              <input class="control control--numero mesas__otra" id="<?= e($idMesa) ?>" type="text" inputmode="numeric" maxlength="2" value="" placeholder="Otra" autocomplete="off" aria-describedby="<?= e($describeMesa) ?>">
            </div>
