/* =====================================================================
   Sistema de Alta Solicitada — SEDES Oruro
   Lógica de la landing page: validación en cliente y llamadas fetch() a
   los scripts PHP (crear_alta.php, generar_pdf.php, verificar_codigo.php,
   subir_adjunto.php). No hay recarga de página en ningún flujo.
   ===================================================================== */
(function () {
  'use strict';

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  var MAX_BYTES = 10 * 1024 * 1024;

  // -------------------------------------------------------------------
  // Utilidades comunes
  // -------------------------------------------------------------------
  function mostrarMensaje(el, texto, tipo) {
    el.textContent = texto;
    el.className = 'mensaje mensaje--' + (tipo || 'info');
    el.hidden = false;
  }

  function ocultarMensaje(el) {
    el.hidden = true;
    el.textContent = '';
  }

  function limpiarErrores(form) {
    $$('.campo__error', form).forEach(function (p) {
      p.textContent = '';
      p.classList.remove('visible');
    });
    $$('.campo--invalido', form).forEach(function (c) {
      c.classList.remove('campo--invalido');
    });
  }

  function marcarError(form, nombreCampo, texto) {
    var p = $('[data-error-de="' + nombreCampo + '"]', form);
    if (!p) { return; }
    p.textContent = texto;
    p.classList.add('visible');
    var contenedor = p.closest('.campo');
    if (contenedor) { contenedor.classList.add('campo--invalido'); }
  }

  function enfocarPrimerError(form) {
    var primero = $('.campo--invalido input, .campo--invalido textarea', form);
    if (primero) {
      primero.focus();
      primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /** Lee la respuesta como JSON tolerando respuestas no-JSON del servidor. */
  function leerJson(respuesta) {
    return respuesta.text().then(function (texto) {
      try {
        return JSON.parse(texto);
      } catch (e) {
        return { ok: false, error: 'El servidor devolvió una respuesta inesperada.' };
      }
    });
  }

  function ocupado(boton, activo, textoOcupado) {
    if (activo) {
      boton.dataset.textoOriginal = boton.textContent;
      boton.textContent = textoOcupado || 'Procesando…';
      boton.disabled = true;
    } else {
      if (boton.dataset.textoOriginal) { boton.textContent = boton.dataset.textoOriginal; }
      boton.disabled = false;
    }
  }

  function formatoFechaHoy() {
    var d = new Date();
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var dd = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + mm + '-' + dd;
  }

  function formatoHoraAhora() {
    var d = new Date();
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function pesoLegible(bytes) {
    if (bytes < 1024) { return bytes + ' B'; }
    if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + ' KB'; }
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  // -------------------------------------------------------------------
  // Pestañas
  // -------------------------------------------------------------------
  $$('.pestana').forEach(function (btn) {
    btn.addEventListener('click', function () {
      $$('.pestana').forEach(function (b) {
        b.classList.remove('pestana--activa');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('pestana--activa');
      btn.setAttribute('aria-selected', 'true');

      $$('.panel').forEach(function (p) { p.hidden = true; });
      $('#' + btn.dataset.panel).hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // ===================================================================
  // SECCIÓN A — Llenado de información
  // ===================================================================
  var formAlta       = $('#form-alta');
  var mensajeAlta    = $('#mensaje-alta');
  var resultadoAlta  = $('#resultado-alta');
  var introLlenado   = $('#panel-llenado .intro');
  var btnGuardar     = $('#btn-guardar');
  var motivo         = $('#motivo_alta');
  var contadorMotivo = $('#contador-motivo');

  $('#btn-nueva-solicitud').addEventListener('click', function () {
    introLlenado.hidden = true;
    resultadoAlta.hidden = true;
    formAlta.hidden = false;
    // Valores por defecto cómodos: la solicitud casi siempre es de hoy.
    if (!$('#fecha_solicitud').value) { $('#fecha_solicitud').value = formatoFechaHoy(); }
    if (!$('#hora_solicitud').value) { $('#hora_solicitud').value = formatoHoraAhora(); }
    $('#nombre_establecimiento').focus();
  });

  $('#btn-cancelar').addEventListener('click', function () {
    formAlta.reset();
    limpiarErrores(formAlta);
    ocultarMensaje(mensajeAlta);
    contadorMotivo.textContent = '0';
    formAlta.hidden = true;
    introLlenado.hidden = false;
  });

  $('#btn-otra-solicitud').addEventListener('click', function () {
    formAlta.reset();
    limpiarErrores(formAlta);
    ocultarMensaje(mensajeAlta);
    contadorMotivo.textContent = '0';
    resultadoAlta.hidden = true;
    introLlenado.hidden = false;
  });

  motivo.addEventListener('input', function () {
    contadorMotivo.textContent = String(motivo.value.length);
  });

  /** Validación en cliente antes de enviar (el servidor vuelve a validar). */
  function validarFormularioAlta() {
    limpiarErrores(formAlta);
    var ok = true;

    var obligatorios = {
      nombre_establecimiento:  'Indique el nombre del establecimiento de salud.',
      servicio_unidad:         'Indique el servicio o unidad.',
      nombre_paciente:         'Indique los nombres y apellidos del paciente.',
      numero_historia_clinica: 'Indique el número de historia clínica.',
      domicilio:               'Indique el domicilio del paciente.',
      fecha_internacion:       'Seleccione la fecha de internación.',
      motivo_alta:             'Describa el motivo de alta expresado por el paciente.',
      fecha_solicitud:         'Seleccione la fecha de la solicitud.',
      hora_solicitud:          'Indique la hora de la solicitud.',
      dni_documento:           'Indique el número de documento de identidad.'
    };

    Object.keys(obligatorios).forEach(function (nombre) {
      var campo = formAlta.elements[nombre];
      if (!campo || campo.value.trim() === '') {
        marcarError(formAlta, nombre, obligatorios[nombre]);
        ok = false;
      }
    });

    var fi = formAlta.elements.fecha_internacion.value;
    var fs = formAlta.elements.fecha_solicitud.value;
    if (ok && fi && fs && fs < fi) {
      marcarError(formAlta, 'fecha_solicitud', 'La fecha de solicitud no puede ser anterior a la de internación.');
      ok = false;
    }

    return ok;
  }

  formAlta.addEventListener('submit', function (evento) {
    evento.preventDefault();
    ocultarMensaje(mensajeAlta);

    if (!validarFormularioAlta()) {
      mostrarMensaje(mensajeAlta, 'Revise los campos marcados en rojo.', 'error');
      enfocarPrimerError(formAlta);
      return;
    }

    var datos = {};
    new FormData(formAlta).forEach(function (valor, clave) { datos[clave] = valor; });

    ocupado(btnGuardar, true, 'Guardando…');

    fetch('crear_alta.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    })
      .then(leerJson)
      .then(function (respuesta) {
        ocupado(btnGuardar, false);

        if (!respuesta.ok) {
          if (respuesta.campos) {
            Object.keys(respuesta.campos).forEach(function (nombre) {
              marcarError(formAlta, nombre, respuesta.campos[nombre]);
            });
            enfocarPrimerError(formAlta);
          }
          mostrarMensaje(mensajeAlta, respuesta.error || 'No fue posible registrar la solicitud.', 'error');
          return;
        }

        var codigo = respuesta.codigo_alta;
        $('#codigo-asignado').textContent = codigo;
        $('#resultado-detalle').textContent =
          'Paciente: ' + respuesta.registro.nombre_paciente + ' — ' + respuesta.registro.servicio_unidad + '.';
        $('#btn-descargar-pdf').href = 'generar_pdf.php?codigo=' + encodeURIComponent(codigo);
        $('#btn-ver-pdf').href = 'generar_pdf.php?codigo=' + encodeURIComponent(codigo) + '&modo=inline';

        formAlta.hidden = true;
        resultadoAlta.hidden = false;
        resultadoAlta.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Precarga el código en la sección de subida, para comodidad.
        $('#codigo_alta').value = codigo;
      })
      .catch(function () {
        ocupado(btnGuardar, false);
        mostrarMensaje(mensajeAlta, 'No se pudo contactar con el servidor. Verifique su conexión.', 'error');
      });
  });

  // ===================================================================
  // SECCIÓN B — Subir alta firmada
  // ===================================================================
  var formSubida      = $('#form-subida');
  var mensajeSubida   = $('#mensaje-subida');
  var resultadoSubida = $('#resultado-subida');
  var introSubida     = $('#panel-subida .intro');
  var btnSubir        = $('#btn-subir');
  var inputCodigo     = $('#codigo_alta');
  var inputArchivo    = $('#archivo');
  var estadoCodigo    = $('#estado-codigo');

  /** Normaliza lo que escribe el usuario: "12" -> "ALTA-000012". */
  function normalizarCodigo(valor) {
    var v = String(valor || '').trim().toUpperCase().replace(/\s+/g, '');
    if (/^\d+$/.test(v)) { v = 'ALTA-' + v.padStart(6, '0'); }
    return v;
  }

  inputCodigo.addEventListener('blur', function () {
    var codigo = normalizarCodigo(inputCodigo.value);
    if (codigo === '') { estadoCodigo.textContent = ''; return; }
    inputCodigo.value = codigo;

    if (!/^ALTA-\d{6,}$/.test(codigo)) {
      estadoCodigo.textContent = 'Formato esperado: ALTA-000001';
      estadoCodigo.className = 'campo__ayuda estado-mal';
      return;
    }

    estadoCodigo.textContent = 'Verificando…';
    estadoCodigo.className = 'campo__ayuda';

    fetch('verificar_codigo.php?codigo=' + encodeURIComponent(codigo))
      .then(leerJson)
      .then(function (r) {
        if (r.ok) {
          estadoCodigo.textContent = 'Código válido — Paciente: ' + r.paciente + ' (' + r.servicio + ').';
          estadoCodigo.className = 'campo__ayuda estado-ok';
        } else {
          estadoCodigo.textContent = r.error || 'Código no encontrado.';
          estadoCodigo.className = 'campo__ayuda estado-mal';
        }
      })
      .catch(function () {
        estadoCodigo.textContent = '';
      });
  });

  var ayudaArchivo      = inputArchivo.parentNode.querySelector('.campo__ayuda');
  var ayudaArchivoTexto = ayudaArchivo.textContent;

  inputArchivo.addEventListener('change', function () {
    limpiarErrores(formSubida);
    var f = inputArchivo.files[0];
    ayudaArchivo.textContent = f ? (f.name + ' — ' + pesoLegible(f.size)) : ayudaArchivoTexto;
  });

  formSubida.addEventListener('submit', function (evento) {
    evento.preventDefault();
    limpiarErrores(formSubida);
    ocultarMensaje(mensajeSubida);

    var codigo  = normalizarCodigo(inputCodigo.value);
    var archivo = inputArchivo.files[0];
    var ok      = true;

    inputCodigo.value = codigo;

    if (codigo === '') {
      marcarError(formSubida, 'codigo_alta', 'Ingrese el código de alta.');
      ok = false;
    } else if (!/^ALTA-\d{6,}$/.test(codigo)) {
      marcarError(formSubida, 'codigo_alta', 'El código debe tener el formato ALTA-000001.');
      ok = false;
    }

    if (!archivo) {
      marcarError(formSubida, 'archivo', 'Adjunte el documento firmado (PDF o JPG).');
      ok = false;
    } else {
      var nombre = archivo.name.toLowerCase();
      if (!/\.(pdf|jpe?g)$/.test(nombre)) {
        marcarError(formSubida, 'archivo', 'Solo se aceptan archivos PDF o JPG.');
        ok = false;
      } else if (archivo.size > MAX_BYTES) {
        marcarError(formSubida, 'archivo', 'El archivo pesa ' + pesoLegible(archivo.size) + '. El máximo es 10 MB.');
        ok = false;
      }
    }

    if (!ok) {
      mostrarMensaje(mensajeSubida, 'Revise los campos marcados en rojo.', 'error');
      enfocarPrimerError(formSubida);
      return;
    }

    var cuerpo = new FormData();
    cuerpo.append('codigo_alta', codigo);
    cuerpo.append('archivo', archivo);

    ocupado(btnSubir, true, 'Subiendo…');

    fetch('subir_adjunto.php', { method: 'POST', body: cuerpo })
      .then(leerJson)
      .then(function (respuesta) {
        ocupado(btnSubir, false);

        if (!respuesta.ok) {
          mostrarMensaje(mensajeSubida, respuesta.error || 'No fue posible subir el documento.', 'error');
          return;
        }

        $('#subida-codigo').textContent = respuesta.codigo_alta;
        $('#subida-detalle').textContent =
          'Paciente: ' + respuesta.paciente + '. Archivo «' + respuesta.adjunto.nombre_original + '» (' +
          pesoLegible(respuesta.adjunto.tamano_bytes) + ') recibido el ' + respuesta.adjunto.recibido_en +
          '. Estado del registro: verificado.';

        formSubida.hidden = true;
        introSubida.hidden = true;
        resultadoSubida.hidden = false;
        resultadoSubida.scrollIntoView({ behavior: 'smooth', block: 'center' });
      })
      .catch(function () {
        ocupado(btnSubir, false);
        mostrarMensaje(mensajeSubida, 'No se pudo contactar con el servidor. Verifique su conexión.', 'error');
      });
  });

  $('#btn-limpiar-subida').addEventListener('click', function () {
    formSubida.reset();
    limpiarErrores(formSubida);
    ocultarMensaje(mensajeSubida);
    estadoCodigo.textContent = '';
    estadoCodigo.className = 'campo__ayuda';
    ayudaArchivo.textContent = ayudaArchivoTexto;
  });

  $('#btn-otra-subida').addEventListener('click', function () {
    formSubida.reset();
    limpiarErrores(formSubida);
    ocultarMensaje(mensajeSubida);
    estadoCodigo.textContent = '';
    estadoCodigo.className = 'campo__ayuda';
    ayudaArchivo.textContent = ayudaArchivoTexto;
    resultadoSubida.hidden = true;
    introSubida.hidden = false;
    formSubida.hidden = false;
  });
})();
