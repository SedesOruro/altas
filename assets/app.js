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
    $$('[aria-invalid="true"]', form).forEach(function (control) {
      control.removeAttribute('aria-invalid');
    });
  }

  /** Control real de un campo, sea input, select o el botón del calendario. */
  function controlDe(form, nombreCampo) {
    var control = form.elements[nombreCampo];
    if (control && control.type === 'hidden') {
      // Las fechas son inputs ocultos: quien recibe el foco es su botón.
      return $('#' + nombreCampo + '_boton', form) || control;
    }
    return control || null;
  }

  function marcarError(form, nombreCampo, texto) {
    var p = $('[data-error-de="' + nombreCampo + '"]', form);
    if (!p) { return; }
    p.textContent = texto;
    p.classList.add('visible');

    var contenedor = p.closest('.campo');
    if (contenedor) { contenedor.classList.add('campo--invalido'); }

    // aria-invalid lo anuncia el lector de pantalla; el texto ya está
    // enlazado con aria-describedby desde prepararCampos().
    var control = controlDe(form, nombreCampo);
    if (control && control.setAttribute) { control.setAttribute('aria-invalid', 'true'); }
  }

  /**
   * Enlaza cada control con su texto de ayuda y su mensaje de error, para
   * que el lector de pantalla los lea junto al campo. Se hace aquí y no en
   * el HTML porque los identificadores se derivan del nombre del campo.
   */
  function prepararCampos(form) {
    $$('.campo__error', form).forEach(function (p) {
      var nombre = p.getAttribute('data-error-de');
      if (!nombre) { return; }

      p.id = 'error-' + nombre;

      var control = controlDe(form, nombre);
      if (!control || !control.setAttribute) { return; }

      var descriptores = [];
      var ayuda = p.closest('.campo') ? p.closest('.campo').querySelector('.campo__ayuda') : null;
      if (ayuda) {
        ayuda.id = ayuda.id || 'ayuda-' + nombre;
        descriptores.push(ayuda.id);
      }
      descriptores.push(p.id);

      control.setAttribute('aria-describedby', descriptores.join(' '));
    });
  }

  /** Asigna un valor por defecto y avisa a quien escuche (selector de fecha). */
  function ponerValor(campo, valor) {
    if (!campo || campo.value) { return; }
    campo.value = valor;
    campo.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function enfocarPrimerError(form) {
    // Los campos de fecha son <input type="hidden">: se enfoca su botón.
    var primero = $('.campo--invalido input:not([type="hidden"]), .campo--invalido select, ' +
                    '.campo--invalido textarea, .campo--invalido .fecha__boton', form);
    if (primero) {
      primero.focus({ preventScroll: true });
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
      if (btn.classList.contains('pestana--activa')) { return; }

      // Salir de la pestaña con el formulario abierto equivale a cancelar:
      // el llenado a medias no se conserva al volver.
      if (btn.dataset.panel !== 'panel-llenado') { cancelarSolicitudEnCurso(); }

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
    ponerValor($('#fecha_solicitud'), formatoFechaHoy());
    ponerValor($('#hora_solicitud'), formatoHoraAhora());
    actualizarProgreso();
    $('#red_salud').focus();
  });

  /** Deja la Sección A como al entrar: solo el botón «Nueva Solicitud de Alta». */
  function reiniciarSeccionLlenado() {
    formAlta.reset();

    // Los campos de fecha son <input type="hidden"> creados por el selector de
    // calendario. En un input oculto la propiedad `value` escribe el atributo
    // `value`, que es justamente el valor por defecto: por eso `reset()` no los
    // limpia y hay que vaciarlos a mano, avisando con `change` para que el
    // botón visible del calendario vuelva a su texto inicial.
    $$('input[type="hidden"]', formAlta).forEach(function (campo) {
      campo.value = '';
      campo.dispatchEvent(new Event('change', { bubbles: true }));
    });

    limpiarErrores(formAlta);
    ocultarMensaje(mensajeAlta);
    contadorMotivo.textContent = '0';
    aplicarRed(false);   // el municipio y los establecimientos vuelven a cero
    mostrarResumenErrores([]);
    $$('[data-tocado]', formAlta).forEach(function (c) { delete c.dataset.tocado; });
    actualizarProgreso();
    formAlta.hidden = true;
    resultadoAlta.hidden = true;
    introLlenado.hidden = false;
  }

  /**
   * Cancela un llenado a medias. Si lo que está a la vista es el resultado de
   * un alta ya guardada, no se toca: el usuario suele pasar a la Sección B
   * justamente para subir ese documento.
   */
  function cancelarSolicitudEnCurso() {
    if (!formAlta.hidden) { reiniciarSeccionLlenado(); }
  }

  $('#btn-cancelar').addEventListener('click', reiniciarSeccionLlenado);
  $('#btn-otra-solicitud').addEventListener('click', reiniciarSeccionLlenado);

  motivo.addEventListener('input', function () {
    contadorMotivo.textContent = String(motivo.value.length);
  });

  // -------------------------------------------------------------------
  // Red de Salud → Municipio y Establecimiento
  //
  // La red es el primer campo y manda sobre los otros dos: fija el
  // municipio y acota la lista de establecimientos. Cuando la red tiene un
  // solo establecimiento, queda elegido sin que el usuario haga nada.
  // El catálogo lo publica la página desde lib/redes.php, que es el mismo
  // que usa el servidor para validar.
  // -------------------------------------------------------------------
  var catalogoRedes  = window.CATALOGO_REDES || {};
  var selectRed      = $('#red_salud');
  var campoMunicipio = $('#municipio');
  var selectEstab    = $('#nombre_establecimiento');

  function aplicarRed(conservarEstablecimiento) {
    var red  = selectRed.value;
    var info = catalogoRedes[red];

    if (!info) {
      campoMunicipio.value = '';
      selectEstab.innerHTML = '<option value="">Elija primero la red de salud</option>';
      selectEstab.disabled = true;
      return;
    }

    campoMunicipio.value = info.municipio;

    var anterior = conservarEstablecimiento ? selectEstab.value : '';
    var lista    = info.establecimientos;
    var opciones = '';

    // Con un único establecimiento no se pide elegir: se asigna.
    if (lista.length > 1) {
      opciones += '<option value="">Seleccione el establecimiento…</option>';
    }
    lista.forEach(function (nombre) {
      opciones += '<option value="' + nombre.replace(/"/g, '&quot;') + '">' + nombre + '</option>';
    });

    selectEstab.innerHTML = opciones;
    selectEstab.disabled = false;
    selectEstab.value = (lista.indexOf(anterior) !== -1) ? anterior : lista[0];

    // Con varios establecimientos la elección es del usuario.
    if (lista.length > 1 && lista.indexOf(anterior) === -1) {
      selectEstab.value = '';
    }
  }

  selectRed.addEventListener('change', function () {
    aplicarRed(false);
    limpiarErrores(formAlta);
  });

  aplicarRed(false);

  // -------------------------------------------------------------------
  // Validación al salir del campo y progreso por secciones
  //
  // Avisar al abandonar un campo evita llegar al final con diez errores de
  // golpe. Se valida al salir del campo, nunca mientras se escribe:
  // corregir a alguien a mitad de palabra molesta más de lo que ayuda.
  // -------------------------------------------------------------------
  prepararCampos(formAlta);

  var SECCIONES = ['Establecimiento', 'Paciente', 'Internación', 'Declaración', 'Firmas'];

  /** ¿Están completos los campos obligatorios de esta sección? */
  function seccionCompleta(seccion) {
    return $$('input[required], select[required], textarea[required]', seccion)
      .every(function (control) { return String(control.value).trim() !== ''; });
  }

  function actualizarProgreso() {
    var secciones = $$('#form-alta [data-seccion]');
    if (!secciones.length) { return; }

    var completas = 0;
    secciones.forEach(function (seccion) {
      var lista = seccionCompleta(seccion);
      seccion.classList.toggle('bloque--completo', lista);
      if (lista) { completas++; }
    });

    var relleno = $('#progreso-relleno');
    var conteo  = $('#progreso-conteo');
    var texto   = $('#progreso-texto');

    if (relleno) { relleno.style.width = (completas / secciones.length * 100) + '%'; }
    if (conteo)  { conteo.textContent = completas + ' / ' + secciones.length + ' completas'; }

    // El rótulo señala la primera sección que falta: es la que toca atender.
    if (texto) {
      var pendiente = secciones.filter(function (sec) { return !seccionCompleta(sec); })[0];
      if (pendiente) {
        var indice = Number(pendiente.getAttribute('data-seccion'));
        texto.textContent = 'Sección ' + indice + ' de ' + secciones.length + ' · ' + SECCIONES[indice - 1];
      } else {
        texto.textContent = 'Todas las secciones están completas';
      }
    }
  }

  /** Quita el error de un campo en cuanto queda corregido. */
  function revalidarCampo(nombre) {
    var p = $('[data-error-de="' + nombre + '"]', formAlta);
    if (!p || !p.classList.contains('visible')) { return; }

    var control = formAlta.elements[nombre];
    if (control && String(control.value).trim() !== '') {
      p.textContent = '';
      p.classList.remove('visible');
      var contenedor = p.closest('.campo');
      if (contenedor) { contenedor.classList.remove('campo--invalido'); }
      var foco = controlDe(formAlta, nombre);
      if (foco && foco.removeAttribute) { foco.removeAttribute('aria-invalid'); }

      // El resumen del encabezado también se descuenta: dejar ahí un error
      // ya corregido haría dudar de si se arregló o no.
      var item = $('#resumen-errores [data-ir-a="' + nombre + '"]');
      if (item) {
        var li = item.closest('li');
        if (li) { li.remove(); }
        var caja = $('#resumen-errores');
        if (caja && !caja.querySelectorAll('li').length) { caja.hidden = true; }
      }
    }
  }

  // `blur` no burbujea: se escucha en fase de captura.
  formAlta.addEventListener('blur', function (evento) {
    var control = evento.target;
    if (!control.name || !control.form) { return; }

    // Solo se avisa de un campo obligatorio que se dejó vacío tras visitarlo;
    // nunca de uno al que el usuario todavía no ha llegado.
    if (control.required && String(control.value).trim() === '' && control.dataset.tocado === '1') {
      var p = $('[data-error-de="' + control.name + '"]', formAlta);
      if (p && !p.classList.contains('visible')) {
        marcarError(formAlta, control.name, 'Este campo es obligatorio.');
      }
    }
    control.dataset.tocado = '1';
    actualizarProgreso();
  }, true);

  ['input', 'change'].forEach(function (evt) {
    formAlta.addEventListener(evt, function (evento) {
      if (evento.target.name) { revalidarCampo(evento.target.name); }
      actualizarProgreso();
    });
  });

  /**
   * Comprueba el formulario y devuelve la lista de problemas en el orden en
   * que aparecen en pantalla. Devolver la lista (y no un simple sí/no)
   * permite construir con ella el resumen de errores del encabezado.
   */
  function validarFormularioAlta() {
    limpiarErrores(formAlta);
    var errores = [];

    var obligatorios = {
      red_salud:               'Seleccione la red de salud.',
      municipio:               'El municipio se completa al elegir la red.',
      nombre_establecimiento:  'Seleccione el establecimiento de salud.',
      servicio_unidad:         'Indique el servicio o unidad.',
      nombre_paciente:         'Indique los nombres y apellidos del paciente.',
      edad:                    'Indique la edad del paciente.',
      sexo:                    'Seleccione el sexo del paciente.',
      numero_historia_clinica: 'Indique el número de historia clínica.',
      domicilio:               'Indique el domicilio del paciente.',
      fecha_internacion:       'Seleccione la fecha de internación.',
      hora_internacion:        'Indique la hora de internación.',
      diagnosticos_ingreso:    'Indique los diagnósticos de ingreso.',
      fecha_solicitud:         'Seleccione la fecha del alta solicitada.',
      hora_solicitud:          'Indique la hora de la solicitud.',
      diagnosticos_egreso:     'Indique los diagnósticos de egreso.',
      motivo_alta:             'Describa el motivo de alta expresado por el paciente.',
      ci_pasaporte:            'Indique el número de cédula de identidad o pasaporte.'
    };

    Object.keys(obligatorios).forEach(function (nombre) {
      var campo = formAlta.elements[nombre];
      if (!campo || String(campo.value).trim() === '') {
        errores.push({ campo: nombre, mensaje: obligatorios[nombre] });
      }
    });

    var edad = formAlta.elements.edad.value.trim();
    if (edad !== '' && (!/^\d{1,3}$/.test(edad) || Number(edad) > 130)) {
      errores.push({ campo: 'edad', mensaje: 'La edad debe ser un número entero válido.' });
    }

    // El alta no puede producirse antes del ingreso.
    var ingreso = formAlta.elements.fecha_internacion.value + 'T' + formAlta.elements.hora_internacion.value;
    var salida  = formAlta.elements.fecha_solicitud.value + 'T' + formAlta.elements.hora_solicitud.value;
    if (!errores.length && ingreso.length > 11 && salida.length > 11 && salida < ingreso) {
      errores.push({
        campo: 'fecha_solicitud',
        mensaje: 'La fecha y hora del alta no pueden ser anteriores a las de la internación.'
      });
    }

    // Se ordenan según la posición real del campo en la página, para que el
    // resumen siga el mismo recorrido que hará el usuario al corregirlos.
    var orden = $$('.campo__error', formAlta).map(function (p) { return p.getAttribute('data-error-de'); });
    errores.sort(function (a, b) { return orden.indexOf(a.campo) - orden.indexOf(b.campo); });

    errores.forEach(function (e) { marcarError(formAlta, e.campo, e.mensaje); });
    return errores;
  }

  /**
   * Resumen de errores al inicio del formulario. Complementa —no sustituye—
   * los mensajes de cada campo: enlaza con ellos y recibe el foco, que es lo
   * que permite corregir sin buscar a ciegas en un formulario largo.
   */
  function mostrarResumenErrores(errores) {
    var caja  = $('#resumen-errores');
    var lista = $('#resumen-errores-lista');
    if (!caja || !lista) { return; }

    if (!errores.length) {
      caja.hidden = true;
      lista.innerHTML = '';
      return;
    }

    lista.innerHTML = errores.map(function (e) {
      var control = controlDe(formAlta, e.campo);
      var destino = control && control.id ? control.id : '';
      var texto   = e.mensaje.replace(/&/g, '&amp;').replace(/</g, '&lt;');
      return destino
        ? '<li><a href="#' + destino + '" data-ir-a="' + e.campo + '">' + texto + '</a></li>'
        : '<li>' + texto + '</li>';
    }).join('');

    caja.hidden = false;
    caja.focus();
  }

  // Los enlaces del resumen llevan el foco al campo, no solo a su posición.
  var resumenErrores = $('#resumen-errores');
  if (resumenErrores) {
    resumenErrores.addEventListener('click', function (e) {
      var enlace = e.target.closest('[data-ir-a]');
      if (!enlace) { return; }
      e.preventDefault();
      var control = controlDe(formAlta, enlace.getAttribute('data-ir-a'));
      if (control) {
        control.focus({ preventScroll: true });
        control.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  formAlta.addEventListener('submit', function (evento) {
    evento.preventDefault();
    ocultarMensaje(mensajeAlta);

    var errores = validarFormularioAlta();
    if (errores.length) {
      mostrarResumenErrores(errores);
      ocultarMensaje(mensajeAlta);
      return;
    }
    mostrarResumenErrores([]);

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
            var delServidor = Object.keys(respuesta.campos).map(function (nombre) {
              return { campo: nombre, mensaje: respuesta.campos[nombre] };
            });
            delServidor.forEach(function (e) { marcarError(formAlta, e.campo, e.mensaje); });
            mostrarResumenErrores(delServidor);
          }
          mostrarMensaje(mensajeAlta, respuesta.error || 'No fue posible registrar la solicitud.', 'error');
          return;
        }

        var codigo = respuesta.codigo_alta;
        $('#codigo-asignado').textContent = codigo;
        $('#resultado-detalle').textContent =
          'Paciente: ' + respuesta.registro.nombre_paciente + ' — ' +
          respuesta.registro.servicio_unidad + ', ' + respuesta.registro.nombre_establecimiento + '.';
        $('#btn-descargar-pdf').href = 'generar_pdf.php?codigo=' + encodeURIComponent(codigo);
        $('#btn-ver-pdf').href = 'generar_pdf.php?codigo=' + encodeURIComponent(codigo) + '&modo=inline';

        formAlta.hidden = true;
        introLlenado.hidden = true;
        resultadoAlta.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });

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
