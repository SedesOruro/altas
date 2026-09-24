<?php
/**
 * Campos «Red de salud» y «Establecimiento» del alta de usuarios.
 *
 * Los usan las dos pantallas que tocan una cuenta —registro.php y
 * admin/usuario_editar.php—, para que no puedan divergir. Las opciones
 * salen de `lib/redes.php`, el mismo catálogo con el que el servidor
 * valida lo que llega.
 *
 * Solo tienen sentido para un operador: el administrador no está atado a
 * ningún establecimiento. De eso se encarga assets/usuario-establecimiento.js,
 * que los habilita o deshabilita según el rol elegido.
 */

require_once __DIR__ . '/redes.php';

/**
 * Pinta los dos campos.
 *
 * @param array $datos   Valores actuales ('red_salud', 'nombre_establecimiento').
 * @param array $errores Errores por campo, tal como los devuelve validar_datos_usuario().
 */
function campos_establecimiento(array $datos, array $errores)
{
    $red   = isset($datos['red_salud']) ? (string) $datos['red_salud'] : '';
    $esta  = isset($datos['nombre_establecimiento']) ? (string) $datos['nombre_establecimiento'] : '';
    $malR  = isset($errores['red_salud']) ? ' is-invalid' : '';
    $malE  = isset($errores['nombre_establecimiento']) ? ' is-invalid' : '';
    ?>
    <div class="form-group col-md-6" data-campo-establecimiento>
      <label for="red_salud">Red de salud <span class="text-danger">*</span></label>
      <select class="form-control<?= $malR ?>" id="red_salud" name="red_salud">
        <option value="">Seleccione la red…</option>
        <?php foreach (catalogo_redes() as $nombre => $datosRed): ?>
          <option value="<?= h($nombre) ?>"<?= $red === $nombre ? ' selected' : '' ?>>
            <?= h($nombre) ?> — <?= h($datosRed['municipio']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errores['red_salud'])): ?>
        <div class="invalid-feedback d-block"><?= h($errores['red_salud']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group col-md-6" data-campo-establecimiento>
      <label for="nombre_establecimiento">Establecimiento <span class="text-danger">*</span></label>
      <select class="form-control<?= $malE ?>" id="nombre_establecimiento" name="nombre_establecimiento"
              data-seleccionado="<?= h($esta) ?>">
        <option value="">Seleccione primero la red…</option>
      </select>
      <small class="form-text text-muted">
        El operador registrará todas sus altas a nombre de este establecimiento.
      </small>
      <?php if (isset($errores['nombre_establecimiento'])): ?>
        <div class="invalid-feedback d-block"><?= h($errores['nombre_establecimiento']) ?></div>
      <?php endif; ?>
    </div>
    <?php
}

/** Catálogo y script que enlazan red → establecimiento y rol → ambos. */
function script_establecimiento($base)
{
    ?>
    <script>window.CATALOGO_REDES = <?= catalogo_redes_json() ?>;</script>
    <script src="<?= h($base) ?>assets/usuario-establecimiento.js?v=1"></script>
    <?php
}
