<?php
/**
 * Formulario público de altas.
 *
 * Es PHP y no HTML plano por un solo motivo: las opciones de «Red de Salud»
 * y de «Establecimiento» se pintan desde `lib/redes.php`, el mismo catálogo
 * que usa el servidor para validar lo que llega. Así no hay dos listas que
 * mantener en paralelo.
 */
require_once __DIR__ . '/lib/redes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alta Solicitada — SEDES Oruro</title>
<meta name="description" content="Sistema de notificación de alta solicitada del Servicio Departamental de Salud de Oruro.">
<link rel="icon" href="assets/logo-sedes.png" type="image/png">
<link rel="stylesheet" href="assets/style.css?v=14">
</head>
<body>

<a class="saltar" href="#contenido">Saltar al contenido</a>

<header class="cabecera">
  <div class="contenedor cabecera__interior">
    <img class="cabecera__escudo" src="assets/logo-sedes.png" alt="Logotipo del Servicio Departamental de Salud de Oruro">
    <div class="cabecera__texto">
      <p class="cabecera__institucion">Servicio Departamental de Salud — Oruro</p>
      <h1 class="cabecera__titulo">Formulario de Notificación de Alta Solicitada</h1>
    </div>
    <a class="cabecera__volver" href="index.html">← Inicio</a>
  </div>
</header>

<nav class="pestanas" aria-label="Secciones del sistema">
  <div class="contenedor pestanas__interior">
    <button type="button" class="pestana pestana--activa" data-panel="panel-llenado" aria-selected="true">
      1. Llenado de información
    </button>
    <button type="button" class="pestana" data-panel="panel-subida" aria-selected="false">
      2. Subir alta firmada
    </button>
  </div>
</nav>

<main class="contenedor" id="contenido">

  <!-- ================================================================
       SECCIÓN A — LLENADO DE INFORMACIÓN
       ================================================================ -->
  <section id="panel-llenado" class="panel">

    <div class="intro">
      <p>
        Complete el formulario para registrar la solicitud de alta voluntaria. Al guardar,
        el sistema asignará un <strong>código de alta correlativo único</strong> y generará el
        documento en PDF con el formato oficial, listo para imprimir y firmar.
      </p>
      <button type="button" id="btn-nueva-solicitud" class="boton boton--primario">
        Nueva Solicitud de Alta
      </button>
    </div>

    <form id="form-alta" class="formulario" novalidate hidden>

      <p class="leyenda-obligatorios">
        Los campos marcados con <span class="req" aria-hidden="true">*</span>
        <span class="visualmente-oculto">asterisco</span> son obligatorios.
      </p>

      <!-- Resumen de errores: se rellena al fallar el envío, recibe el foco
           y enlaza con cada campo. Los errores por campo se mantienen. -->
      <div class="resumen-errores" id="resumen-errores" role="alert" tabindex="-1"
           aria-labelledby="resumen-errores-titulo" hidden>
        <p class="resumen-errores__titulo" id="resumen-errores-titulo">Revise estos campos</p>
        <ul id="resumen-errores-lista"></ul>
      </div>

      <!-- Con cinco secciones conviene saber cuánto falta. -->
      <div class="progreso" aria-hidden="true">
        <div class="progreso__fila">
          <span class="progreso__texto" id="progreso-texto">Sección 1 de 5 · Establecimiento</span>
          <span class="progreso__conteo" id="progreso-conteo">0 / 5 completas</span>
        </div>
        <div class="progreso__barra">
          <span class="progreso__relleno" id="progreso-relleno"></span>
        </div>
      </div>

      <!-- 1. Establecimiento -->
      <fieldset class="bloque" id="seccion-1" data-seccion="1">
        <legend class="bloque__titulo"><span class="bloque__numero">1</span> Datos del Establecimiento de Salud</legend>
        <div class="grilla">
          <!-- La red manda: al elegirla se completan el municipio y la lista
               de establecimientos que le corresponden. -->
          <div class="campo">
            <label for="red_salud">Red de Salud <span class="req">*</span></label>
            <select id="red_salud" name="red_salud" required>
              <option value="">Seleccione la red…</option>
              <?php foreach (array_keys(catalogo_redes()) as $red): ?>
                <option value="<?= htmlspecialchars($red, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($red, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <p class="campo__error" data-error-de="red_salud"></p>
          </div>

          <div class="campo">
            <label for="municipio">Municipio <span class="req">*</span></label>
            <input type="text" id="municipio" name="municipio" maxlength="255"
                   autocomplete="off" readonly required
                   placeholder="Se completa según la red">
            <p class="campo__ayuda">Se asigna automáticamente al elegir la red.</p>
            <p class="campo__error" data-error-de="municipio"></p>
          </div>

          <div class="campo campo--ancho">
            <label for="nombre_establecimiento">Nombre del Establecimiento de Salud <span class="req">*</span></label>
            <select id="nombre_establecimiento" name="nombre_establecimiento" required disabled>
              <option value="">Elija primero la red de salud</option>
            </select>
            <p class="campo__error" data-error-de="nombre_establecimiento"></p>
          </div>

          <div class="campo campo--ancho">
            <label for="servicio_unidad">Servicio/Unidad <span class="req">*</span></label>
            <input type="text" id="servicio_unidad" name="servicio_unidad" maxlength="255" autocomplete="off" required>
            <p class="campo__error" data-error-de="servicio_unidad"></p>
          </div>
        </div>
      </fieldset>

      <!-- 2. Paciente -->
      <fieldset class="bloque" id="seccion-2" data-seccion="2">
        <legend class="bloque__titulo"><span class="bloque__numero">2</span> Información del Paciente</legend>
        <div class="grilla">
          <div class="campo campo--ancho">
            <label for="nombre_paciente">Nombres y Apellidos <span class="req">*</span></label>
            <input type="text" id="nombre_paciente" name="nombre_paciente" maxlength="255" autocomplete="off" required>
            <p class="campo__error" data-error-de="nombre_paciente"></p>
          </div>
          <div class="campo">
            <label for="edad">Edad <span class="req">*</span></label>
            <div class="campo__compuesto">
              <input type="number" id="edad" name="edad" min="0" max="130" step="1" inputmode="numeric" required>
              <select id="edad_unidad" name="edad_unidad">
                <option value="anios" selected>años</option>
                <option value="meses">meses</option>
                <option value="dias">días</option>
              </select>
            </div>
            <p class="campo__error" data-error-de="edad"></p>
          </div>
          <div class="campo">
            <label for="sexo">Sexo <span class="req">*</span></label>
            <select id="sexo" name="sexo" required>
              <option value="">Seleccione…</option>
              <option value="F">Femenino</option>
              <option value="M">Masculino</option>
            </select>
            <p class="campo__error" data-error-de="sexo"></p>
          </div>
          <div class="campo">
            <label for="numero_historia_clinica">Número de Historia Clínica <span class="req">*</span></label>
            <input type="text" id="numero_historia_clinica" name="numero_historia_clinica" maxlength="100" autocomplete="off" required>
            <p class="campo__error" data-error-de="numero_historia_clinica"></p>
          </div>
          <div class="campo">
            <label for="numero_referencia">Número de Referencia <span class="opcional">(opcional)</span></label>
            <input type="text" id="numero_referencia" name="numero_referencia" maxlength="100" autocomplete="off">
            <p class="campo__error" data-error-de="numero_referencia"></p>
          </div>
          <div class="campo campo--ancho">
            <label for="domicilio">Domicilio <span class="req">*</span></label>
            <input type="text" id="domicilio" name="domicilio" maxlength="255" autocomplete="off" required>
            <p class="campo__error" data-error-de="domicilio"></p>
          </div>
        </div>
      </fieldset>

      <!-- 3. Internación -->
      <fieldset class="bloque" id="seccion-3" data-seccion="3">
        <legend class="bloque__titulo"><span class="bloque__numero">3</span> Detalles de la Internación</legend>
        <div class="grilla">
          <div class="campo">
            <label for="fecha_internacion">Fecha de Internación <span class="req">*</span></label>
            <input type="date" id="fecha_internacion" name="fecha_internacion"
                   data-marcador="Elegir fecha de internación" required>
            <p class="campo__error" data-error-de="fecha_internacion"></p>
          </div>
          <div class="campo">
            <label for="hora_internacion">Hora de Internación <span class="req">*</span></label>
            <input type="time" id="hora_internacion" name="hora_internacion" required>
            <p class="campo__error" data-error-de="hora_internacion"></p>
          </div>
          <div class="campo campo--ancho">
            <label for="diagnosticos_ingreso">Diagnósticos de Ingreso <span class="req">*</span></label>
            <input type="text" id="diagnosticos_ingreso" name="diagnosticos_ingreso" maxlength="300" autocomplete="off" required>
            <p class="campo__error" data-error-de="diagnosticos_ingreso"></p>
          </div>
          <div class="campo">
            <label for="fecha_solicitud">Fecha de Alta Solicitada <span class="req">*</span></label>
            <input type="date" id="fecha_solicitud" name="fecha_solicitud"
                   data-marcador="Elegir fecha del alta" required>
            <p class="campo__error" data-error-de="fecha_solicitud"></p>
          </div>
          <div class="campo">
            <label for="hora_solicitud">Hora de Solicitud <span class="req">*</span></label>
            <input type="time" id="hora_solicitud" name="hora_solicitud" required>
            <p class="campo__error" data-error-de="hora_solicitud"></p>
          </div>
          <div class="campo campo--ancho">
            <label for="diagnosticos_egreso">Diagnósticos de Egreso <span class="req">*</span></label>
            <input type="text" id="diagnosticos_egreso" name="diagnosticos_egreso" maxlength="300" autocomplete="off" required>
            <p class="campo__error" data-error-de="diagnosticos_egreso"></p>
          </div>
        </div>
      </fieldset>

      <!-- 4. Declaración -->
      <fieldset class="bloque" id="seccion-4" data-seccion="4">
        <legend class="bloque__titulo"><span class="bloque__numero">4</span> Declaración de Alta Solicitada</legend>
        <p class="declaracion">
          El que suscribe, en pleno uso de sus facultades, solicita la alta voluntaria del
          establecimiento de salud mencionado, asumiendo la responsabilidad total de las
          consecuencias que esta decisión pueda tener sobre su salud, tras haber sido
          informado por el personal médico sobre los riesgos de interrumpir el tratamiento
          o la observación.
        </p>
        <div class="campo">
          <label for="motivo_alta">Motivo de Alta según Paciente <span class="req">*</span></label>
          <textarea id="motivo_alta" name="motivo_alta" rows="6" maxlength="5000" required
                    placeholder="Transcriba el motivo expresado por el paciente."></textarea>
          <p class="campo__ayuda"><span id="contador-motivo">0</span> / 5000 caracteres</p>
          <p class="campo__error" data-error-de="motivo_alta"></p>
        </div>
      </fieldset>

      <!-- 5. Firmas y fecha -->
      <fieldset class="bloque" id="seccion-5" data-seccion="5">
        <legend class="bloque__titulo"><span class="bloque__numero">5</span> Firmas y Fecha</legend>
        <div class="grilla">
          <div class="campo">
            <label for="grado_parentesco">Grado de Parentesco <span class="opcional">(si firma un representante)</span></label>
            <input type="text" id="grado_parentesco" name="grado_parentesco" maxlength="120" autocomplete="off"
                   placeholder="Ej.: madre, hijo, cónyuge">
            <p class="campo__error" data-error-de="grado_parentesco"></p>
          </div>
          <div class="campo">
            <label for="ci_pasaporte">N° de Cédula de Identidad/Pasaporte <span class="req">*</span></label>
            <input type="text" id="ci_pasaporte" name="ci_pasaporte" maxlength="50" autocomplete="off" required>
            <p class="campo__error" data-error-de="ci_pasaporte"></p>
          </div>
        </div>
        <p class="aviso">
          Las firmas <strong>no se capturan digitalmente</strong>. El PDF se imprime con tres
          líneas en blanco —paciente o representante legal, médico, y testigo— para firmarlas
          y sellarlas en físico. El documento firmado se sube después en
          <em>«Subir alta firmada»</em>.
        </p>
      </fieldset>

      <div class="acciones">
        <button type="submit" class="boton boton--primario" id="btn-guardar">Guardar y generar código</button>
        <button type="button" class="boton boton--plano" id="btn-cancelar">Cancelar</button>
      </div>

      <p class="mensaje" id="mensaje-alta" role="status" hidden></p>
    </form>

    <!-- Resultado tras guardar -->
    <div class="resultado" id="resultado-alta" hidden>
      <p class="resultado__etiqueta">Código de alta asignado</p>
      <p class="resultado__codigo" id="codigo-asignado">—</p>
      <p class="resultado__detalle" id="resultado-detalle"></p>
      <div class="acciones">
        <a class="boton boton--primario" id="btn-descargar-pdf" href="#" download>Descargar PDF</a>
        <a class="boton boton--secundario" id="btn-ver-pdf" href="#" target="_blank" rel="noopener">Ver PDF</a>
        <button type="button" class="boton boton--plano" id="btn-otra-solicitud">Registrar otra solicitud</button>
      </div>
      <p class="resultado__nota">
        Anote o conserve este código: será necesario para subir el documento una vez firmado.
      </p>
    </div>

  </section>

  <!-- ================================================================
       SECCIÓN B — SUBIR ALTA FIRMADA
       ================================================================ -->
  <section id="panel-subida" class="panel" hidden>

    <div class="intro">
      <p>
        Suba el formulario de alta ya firmado en físico (escaneado o fotografiado). El sistema
        verificará que el código de alta corresponda a una solicitud registrada antes de
        aceptar el archivo.
      </p>
    </div>

    <form id="form-subida" class="formulario" novalidate enctype="multipart/form-data">
      <fieldset class="bloque">
        <legend class="bloque__titulo">Documento firmado</legend>

        <div class="campo">
          <label for="codigo_alta">Código de Alta <span class="req">*</span></label>
          <input type="text" id="codigo_alta" name="codigo_alta" maxlength="20"
                 placeholder="ALTA-000001" autocomplete="off" spellcheck="false" required>
          <p class="campo__ayuda" id="estado-codigo"></p>
          <p class="campo__error" data-error-de="codigo_alta"></p>
        </div>

        <div class="campo">
          <label for="archivo">Adjuntar Documento <span class="req">*</span></label>
          <input type="file" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" required>
          <p class="campo__ayuda">Formatos aceptados: PDF o JPG. Tamaño máximo: 10 MB.</p>
          <p class="campo__error" data-error-de="archivo"></p>
        </div>
      </fieldset>

      <div class="acciones">
        <button type="submit" class="boton boton--primario" id="btn-subir">Subir documento</button>
        <button type="button" class="boton boton--plano" id="btn-limpiar-subida">Limpiar</button>
      </div>

      <p class="mensaje" id="mensaje-subida" role="status" hidden></p>
    </form>

    <div class="resultado" id="resultado-subida" hidden>
      <p class="resultado__etiqueta">Documento recibido</p>
      <p class="resultado__codigo" id="subida-codigo">—</p>
      <p class="resultado__detalle" id="subida-detalle"></p>
      <div class="acciones">
        <button type="button" class="boton boton--plano" id="btn-otra-subida">Subir otro documento</button>
      </div>
    </div>

  </section>

</main>

<footer class="pie">
  <div class="contenedor">
    <p>SEDES Oruro — Sistema de Notificación de Alta Solicitada</p>
  </div>
</footer>

<script>
  // Catálogo de redes servido desde lib/redes.php: el navegador y el
  // servidor trabajan exactamente con los mismos datos.
  window.CATALOGO_REDES = <?= catalogo_redes_json() ?>;
</script>
<script src="assets/calendario.js?v=14"></script>
<script src="assets/app.js?v=14"></script>
</body>
</html>
