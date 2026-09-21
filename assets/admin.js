/* =====================================================================
   Tabla de altas del panel — SEDES Oruro

   Pide los datos a admin/listar_altas.php y dibuja la tabla. Filtrar,
   cambiar de página o de tamaño de página no recarga la página: solo se
   vuelve a pedir el tramo que corresponde.
   ===================================================================== */
(function () {
  'use strict';

  var $ = function (sel) { return document.querySelector(sel); };

  var cuerpo     = $('#cuerpo-tabla');
  if (!cuerpo) { return; }

  var filtros    = $('#filtros');
  var resumen    = $('#resumen-paginacion');
  var indicador  = $('#indicador-pagina');
  var COLUMNAS   = document.querySelectorAll('#tabla-altas thead th').length;

  var estado = { pagina: 1, paginas: 1, cargando: false };
  var temporizador = null;

  // -------------------------------------------------------------------
  // Utilidades de presentación
  // -------------------------------------------------------------------
  function esc(valor) {
    if (valor === null || valor === undefined || valor === '') { return ''; }
    return String(valor)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fecha(iso) {
    if (!iso) { return ''; }
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso));
    return m ? m[3] + '/' + m[2] + '/' + m[1] : String(iso);
  }

  function hora(valor) {
    if (!valor) { return ''; }
    return String(valor).slice(0, 5);
  }

  function fechaHora(iso) {
    if (!iso) { return ''; }
    var partes = String(iso).split(' ');
    return fecha(partes[0]) + (partes[1] ? ' ' + partes[1].slice(0, 5) : '');
  }

  function edad(alta) {
    var unidades = { anios: 'años', meses: 'meses', dias: 'días' };
    return alta.edad + ' ' + (unidades[alta.edad_unidad] || 'años');
  }

  var ETIQUETA_ESTADO = {
    verificado: ['Verificada', 'badge-success'],
    generado:   ['Pendiente', 'badge-warning'],
    rechazado:  ['Rechazada', 'badge-danger']
  };

  function insignia(valor) {
    var e = ETIQUETA_ESTADO[valor] || [valor, 'badge-secondary'];
    return '<span class="badge ' + e[1] + '">' + esc(e[0]) + '</span>';
  }

  /** Celda de texto largo: se recorta visualmente, completa en el title. */
  function celdaLarga(texto) {
    return '<td class="celda-larga" title="' + esc(texto) + '">' + esc(texto) + '</td>';
  }

  function botones(alta) {
    var html = '<td class="celda-opciones">';

    html += '<a class="btn btn-xs btn-primary" target="_blank" rel="noopener" ' +
            'href="../generar_pdf.php?codigo=' + encodeURIComponent(alta.codigo_alta) + '&modo=inline" ' +
            'title="Ver el PDF generado por el formulario">PDF registrado</a>';

    if (alta.adjunto_id) {
      html += ' <a class="btn btn-xs btn-success" target="_blank" rel="noopener" ' +
              'href="ver_adjunto.php?id=' + encodeURIComponent(alta.adjunto_id) + '" ' +
              'title="Ver el documento firmado que se subió">PDF subido</a>';
    } else {
      html += ' <button type="button" class="btn btn-xs btn-default" disabled ' +
              'title="Todavía no se ha subido el documento firmado">PDF subido</button>';
    }

    return html + '</td>';
  }

  // -------------------------------------------------------------------
  // Dibujo de la tabla
  // -------------------------------------------------------------------
  function mensajeFila(texto, clase) {
    cuerpo.innerHTML = '<tr><td colspan="' + COLUMNAS + '" class="text-center py-4 ' +
                       (clase || 'text-muted') + '">' + esc(texto) + '</td></tr>';
  }

  function pintar(datos) {
    if (!datos.altas.length) {
      mensajeFila('No hay altas que coincidan con los filtros.');
      return;
    }

    var html = datos.altas.map(function (a) {
      return '<tr>' +
        botones(a) +
        '<td class="celda-codigo">' + esc(a.codigo_alta) + '</td>' +
        '<td>' + insignia(a.estado) + '</td>' +
        celdaLarga(a.nombre_paciente) +
        '<td class="text-nowrap">' + esc(edad(a)) + '</td>' +
        '<td>' + (a.sexo === 'F' ? 'Femenino' : 'Masculino') + '</td>' +
        '<td>' + esc(a.numero_historia_clinica) + '</td>' +
        '<td>' + esc(a.numero_referencia || '—') + '</td>' +
        celdaLarga(a.domicilio) +
        celdaLarga(a.nombre_establecimiento) +
        celdaLarga(a.red_salud) +
        '<td>' + esc(a.municipio) + '</td>' +
        celdaLarga(a.servicio_unidad) +
        '<td class="text-nowrap">' + fecha(a.fecha_internacion) + ' ' + hora(a.hora_internacion) + '</td>' +
        celdaLarga(a.diagnosticos_ingreso) +
        '<td class="text-nowrap">' + fecha(a.fecha_solicitud) + ' ' + hora(a.hora_solicitud) + '</td>' +
        celdaLarga(a.diagnosticos_egreso) +
        celdaLarga(a.motivo_alta) +
        '<td>' + esc(a.grado_parentesco || '—') + '</td>' +
        '<td>' + esc(a.ci_pasaporte) + '</td>' +
        '<td class="text-nowrap">' + (a.adjunto_id ? fechaHora(a.adjunto_fecha) : '—') + '</td>' +
        '<td class="text-nowrap">' + fechaHora(a.created_at) + '</td>' +
      '</tr>';
    }).join('');

    cuerpo.innerHTML = html;
  }

  function pintarPaginacion(datos) {
    estado.pagina  = datos.pagina;
    estado.paginas = datos.paginas;

    resumen.textContent = datos.total === 0
      ? 'Sin resultados'
      : 'Mostrando ' + datos.desde_fila + '–' + datos.hasta_fila + ' de ' + datos.total + ' altas';

    indicador.textContent = datos.pagina + ' / ' + datos.paginas;

    $('#btn-primera').disabled  = datos.pagina <= 1;
    $('#btn-anterior').disabled = datos.pagina <= 1;
    $('#btn-siguiente').disabled = datos.pagina >= datos.paginas;
    $('#btn-ultima').disabled    = datos.pagina >= datos.paginas;
  }

  // -------------------------------------------------------------------
  // Carga de datos
  // -------------------------------------------------------------------
  function cargar() {
    if (estado.cargando) { return; }
    estado.cargando = true;

    var parametros = new URLSearchParams(new FormData(filtros));
    parametros.set('pagina', estado.pagina);

    fetch('listar_altas.php?' + parametros.toString(), { credentials: 'same-origin' })
      .then(function (r) {
        // La sesión pudo expirar mientras el panel estaba abierto.
        if (r.status === 401) {
          window.location.href = '../login.php';
          return null;
        }
        return r.json();
      })
      .then(function (datos) {
        estado.cargando = false;
        if (!datos) { return; }
        if (!datos.ok) {
          mensajeFila(datos.error || 'No fue posible cargar las altas.', 'text-danger');
          return;
        }
        pintar(datos);
        pintarPaginacion(datos);
      })
      .catch(function () {
        estado.cargando = false;
        mensajeFila('No se pudo contactar con el servidor.', 'text-danger');
      });
  }

  /** Vuelve a la primera página: un filtro nuevo invalida la página actual. */
  function recargarDesdeElInicio() {
    estado.pagina = 1;
    cargar();
  }

  // -------------------------------------------------------------------
  // Eventos
  // -------------------------------------------------------------------
  filtros.addEventListener('submit', function (e) { e.preventDefault(); });

  // El texto se busca con un respiro, para no consultar en cada tecla.
  $('#f-q').addEventListener('input', function () {
    window.clearTimeout(temporizador);
    temporizador = window.setTimeout(recargarDesdeElInicio, 350);
  });

  ['#f-estado', '#f-desde', '#f-hasta', '#f-por-pagina'].forEach(function (sel) {
    $(sel).addEventListener('change', recargarDesdeElInicio);
  });

  $('#btn-limpiar').addEventListener('click', function () {
    filtros.reset();
    recargarDesdeElInicio();
  });

  $('#btn-primera').addEventListener('click', function () { estado.pagina = 1; cargar(); });
  $('#btn-ultima').addEventListener('click', function () { estado.pagina = estado.paginas; cargar(); });
  $('#btn-anterior').addEventListener('click', function () {
    if (estado.pagina > 1) { estado.pagina--; cargar(); }
  });
  $('#btn-siguiente').addEventListener('click', function () {
    if (estado.pagina < estado.paginas) { estado.pagina++; cargar(); }
  });

  cargar();
})();
