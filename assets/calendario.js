/* =====================================================================
   Selector de fecha táctil — Sistema de Alta Solicitada (SEDES Oruro)

   Sustituye el `<input type="date">` nativo, cuyo comportamiento en móvil
   varía mucho entre navegadores y resulta incómodo de usar. Cada campo se
   convierte en un botón que muestra la fecha en formato boliviano
   (dd/mm/aaaa) y abre un calendario a pantalla completa con:

     - selectores de mes y año, para saltar sin pulsar la flecha doce veces
     - una cuadrícula de días con objetivos táctiles grandes
     - atajos «Hoy» y «Ayer», que cubren casi todos los casos reales

   El `<input>` original no se elimina: se convierte en `hidden` y conserva
   su `name` y su valor `AAAA-MM-DD`, de modo que el formulario, la
   validación y el envío siguen funcionando igual. Si el JavaScript no
   llegara a cargarse, el campo de fecha nativo sigue siendo usable.
   ===================================================================== */
(function () {
  'use strict';

  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
               'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var DIAS_CORTOS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];

  // -------------------------------------------------------------------
  // Utilidades de fecha (siempre en hora local, nunca UTC: `new Date(iso)`
  // interpretaría "2026-09-07" como UTC y podría restar un día).
  // -------------------------------------------------------------------
  function aIso(fecha) {
    return fecha.getFullYear() + '-' +
      String(fecha.getMonth() + 1).padStart(2, '0') + '-' +
      String(fecha.getDate()).padStart(2, '0');
  }

  function desdeIso(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
    if (!m) { return null; }
    var f = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return isNaN(f.getTime()) ? null : f;
  }

  function aTextoLargo(fecha) {
    return String(fecha.getDate()).padStart(2, '0') + '/' +
      String(fecha.getMonth() + 1).padStart(2, '0') + '/' + fecha.getFullYear();
  }

  /** Lunes = 0 … domingo = 6, que es el orden del calendario en Bolivia. */
  function diaSemanaLunes(fecha) {
    return (fecha.getDay() + 6) % 7;
  }

  // -------------------------------------------------------------------
  // Diálogo del calendario: uno solo, reutilizado por todos los campos.
  // -------------------------------------------------------------------
  var dialogo = null;
  var campoActivo = null;
  var mesVisible = null;   // Date situada en el día 1 del mes mostrado

  function crearDialogo() {
    var fondo = document.createElement('div');
    fondo.className = 'calendario__fondo';
    fondo.hidden = true;
    fondo.innerHTML =
      '<div class="calendario" role="dialog" aria-modal="true" aria-label="Seleccionar fecha">' +
        '<div class="calendario__cabecera">' +
          '<button type="button" class="calendario__nav" data-mes="-1" aria-label="Mes anterior">&#8249;</button>' +
          '<div class="calendario__selectores">' +
            '<select class="calendario__mes" aria-label="Mes"></select>' +
            '<select class="calendario__anio" aria-label="Año"></select>' +
          '</div>' +
          '<button type="button" class="calendario__nav" data-mes="1" aria-label="Mes siguiente">&#8250;</button>' +
        '</div>' +
        '<div class="calendario__semana"></div>' +
        '<div class="calendario__dias"></div>' +
        '<div class="calendario__pie">' +
          '<button type="button" class="calendario__atajo" data-atajo="ayer">Ayer</button>' +
          '<button type="button" class="calendario__atajo" data-atajo="hoy">Hoy</button>' +
          '<button type="button" class="calendario__cerrar">Cerrar</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(fondo);

    var caja    = fondo.querySelector('.calendario');
    var selMes  = fondo.querySelector('.calendario__mes');
    var selAnio = fondo.querySelector('.calendario__anio');

    MESES.forEach(function (nombre, i) {
      var op = document.createElement('option');
      op.value = String(i);
      op.textContent = nombre.charAt(0).toUpperCase() + nombre.slice(1);
      selMes.appendChild(op);
    });

    fondo.querySelector('.calendario__semana').innerHTML =
      DIAS_CORTOS.map(function (d) { return '<span>' + d + '</span>'; }).join('');

    // --- Eventos ----------------------------------------------------
    fondo.addEventListener('click', function (e) {
      if (e.target === fondo) { cerrar(); }
    });

    caja.addEventListener('click', function (e) {
      var nav = e.target.closest('.calendario__nav');
      if (nav) {
        mesVisible.setMonth(mesVisible.getMonth() + Number(nav.dataset.mes));
        pintar();
        return;
      }

      var atajo = e.target.closest('.calendario__atajo');
      if (atajo) {
        var f = new Date();
        f.setHours(0, 0, 0, 0);
        if (atajo.dataset.atajo === 'ayer') { f.setDate(f.getDate() - 1); }
        elegir(f);
        return;
      }

      if (e.target.closest('.calendario__cerrar')) { cerrar(); return; }

      var dia = e.target.closest('.calendario__dia');
      if (dia && !dia.disabled) {
        elegir(desdeIso(dia.dataset.iso));
      }
    });

    selMes.addEventListener('change', function () {
      mesVisible.setDate(1);
      mesVisible.setMonth(Number(selMes.value));
      pintar();
    });

    selAnio.addEventListener('change', function () {
      mesVisible.setDate(1);
      mesVisible.setFullYear(Number(selAnio.value));
      pintar();
    });

    document.addEventListener('keydown', function (e) {
      if (fondo.hidden) { return; }
      if (e.key === 'Escape') { cerrar(); return; }

      var saltos = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      if (saltos[e.key]) {
        e.preventDefault();
        var base = desdeIso(campoActivo.input.value) || new Date();
        base.setDate(base.getDate() + saltos[e.key]);
        campoActivo.input.value = aIso(base);
        mesVisible = new Date(base.getFullYear(), base.getMonth(), 1);
        pintar();
      }
    });

    return fondo;
  }

  /** Dibuja la cuadrícula del mes visible. */
  function pintar() {
    var selMes  = dialogo.querySelector('.calendario__mes');
    var selAnio = dialogo.querySelector('.calendario__anio');
    var grilla  = dialogo.querySelector('.calendario__dias');

    // Rango de años: se recalcula por campo, respetando min/max si existen.
    var anioActual = new Date().getFullYear();
    var anioMin = campoActivo.min ? campoActivo.min.getFullYear() : anioActual - 5;
    var anioMax = campoActivo.max ? campoActivo.max.getFullYear() : anioActual + 2;
    var anioVisible = mesVisible.getFullYear();
    anioMin = Math.min(anioMin, anioVisible);
    anioMax = Math.max(anioMax, anioVisible);

    selAnio.innerHTML = '';
    for (var a = anioMax; a >= anioMin; a--) {
      var op = document.createElement('option');
      op.value = String(a);
      op.textContent = String(a);
      selAnio.appendChild(op);
    }
    selAnio.value = String(anioVisible);
    selMes.value = String(mesVisible.getMonth());

    var hoyIso = aIso(new Date());
    var seleccionada = campoActivo.input.value;

    var primero = new Date(anioVisible, mesVisible.getMonth(), 1);
    var huecos  = diaSemanaLunes(primero);
    var ultimo  = new Date(anioVisible, mesVisible.getMonth() + 1, 0).getDate();

    var html = '';
    for (var h = 0; h < huecos; h++) {
      html += '<span class="calendario__hueco"></span>';
    }
    for (var d = 1; d <= ultimo; d++) {
      var fecha = new Date(anioVisible, mesVisible.getMonth(), d);
      var iso   = aIso(fecha);
      var fuera = (campoActivo.min && fecha < campoActivo.min) ||
                  (campoActivo.max && fecha > campoActivo.max);
      var clases = 'calendario__dia';
      if (iso === seleccionada) { clases += ' calendario__dia--elegido'; }
      if (iso === hoyIso)       { clases += ' calendario__dia--hoy'; }
      html += '<button type="button" class="' + clases + '" data-iso="' + iso + '"' +
              (fuera ? ' disabled' : '') + '>' + d + '</button>';
    }
    grilla.innerHTML = html;
  }

  function abrir(campo) {
    campoActivo = campo;
    var actual = desdeIso(campo.input.value) || new Date();
    mesVisible = new Date(actual.getFullYear(), actual.getMonth(), 1);

    dialogo.hidden = false;
    document.body.classList.add('sin-scroll');
    campo.boton.setAttribute('aria-expanded', 'true');
    pintar();
  }

  function cerrar() {
    dialogo.hidden = true;
    document.body.classList.remove('sin-scroll');
    if (campoActivo) {
      campoActivo.boton.setAttribute('aria-expanded', 'false');
      campoActivo.boton.focus();
    }
  }

  function elegir(fecha) {
    if (!fecha) { return; }
    campoActivo.input.value = aIso(fecha);
    // `change` mantiene informado al resto de la aplicación (validaciones,
    // límites entre fechas) igual que lo haría el campo nativo.
    campoActivo.input.dispatchEvent(new Event('change', { bubbles: true }));
    refrescar(campoActivo);
    cerrar();
  }

  /** Vuelca el valor del input oculto en la etiqueta visible del botón. */
  function refrescar(campo) {
    var fecha = desdeIso(campo.input.value);
    campo.texto.textContent = fecha ? aTextoLargo(fecha) : campo.marcador;
    campo.boton.classList.toggle('fecha__boton--vacio', !fecha);
  }

  // -------------------------------------------------------------------
  // Conversión de cada <input type="date"> en un campo táctil.
  // -------------------------------------------------------------------
  function convertir(input) {
    var campo = {
      input: input,
      min: desdeIso(input.getAttribute('min')),
      max: desdeIso(input.getAttribute('max')),
      marcador: input.getAttribute('data-marcador') || 'Seleccione una fecha',
      valorInicial: input.value
    };

    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'fecha__boton';
    boton.setAttribute('aria-haspopup', 'dialog');
    boton.setAttribute('aria-expanded', 'false');
    if (input.id) { boton.id = input.id + '_boton'; }

    var texto = document.createElement('span');
    texto.className = 'fecha__valor';

    var icono = document.createElement('span');
    icono.className = 'fecha__icono';
    icono.setAttribute('aria-hidden', 'true');
    icono.innerHTML =
      '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/>' +
      '<path d="M8 3v4M16 3v4M3 10h18"/></svg>';

    boton.appendChild(texto);
    boton.appendChild(icono);

    // La etiqueta <label for="..."> del campo debe activar el botón nuevo.
    var etiqueta = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
    if (etiqueta && boton.id) { etiqueta.setAttribute('for', boton.id); }

    input.type = 'hidden';
    input.parentNode.insertBefore(boton, input.nextSibling);

    campo.boton = boton;
    campo.texto = texto;

    boton.addEventListener('click', function () { abrir(campo); });
    // Cambios hechos por código (valores por defecto, reset del formulario).
    input.addEventListener('change', function () { refrescar(campo); });

    var formulario = input.form;
    if (formulario) {
      formulario.addEventListener('reset', function () {
        // En un `<input type="hidden">` la propiedad `value` escribe el
        // atributo `value`, que es el valor por defecto del control: por eso
        // `reset()` no lo limpia. Se restaura el valor que tenía la página al
        // cargarse, para que la fecha de un formulario cancelado no reaparezca.
        window.setTimeout(function () {
          campo.input.value = campo.valorInicial;
          refrescar(campo);
        }, 0);
      });
    }

    refrescar(campo);
    return campo;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var campos = Array.prototype.slice.call(document.querySelectorAll('input[type="date"]'));
    if (!campos.length) { return; }
    dialogo = crearDialogo();
    campos.forEach(convertir);
  });
})();
