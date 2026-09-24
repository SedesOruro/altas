/* =====================================================================
   Red → Establecimiento en el alta y la edición de usuarios.

   Dos reglas, las mismas que aplica el servidor:
     - el establecimiento depende de la red elegida;
     - los dos campos solo valen para un operador, porque el administrador
       no está atado a ningún establecimiento.
   ===================================================================== */
(function () {
  'use strict';

  var red   = document.getElementById('red_salud');
  var esta  = document.getElementById('nombre_establecimiento');
  var rol   = document.getElementById('rol');
  if (!red || !esta || !window.CATALOGO_REDES) { return; }

  var bloques = document.querySelectorAll('[data-campo-establecimiento]');

  /** Repuebla el desplegable de establecimientos según la red elegida. */
  function pintarEstablecimientos(preferido) {
    var datos = window.CATALOGO_REDES[red.value];
    var lista = datos ? datos.establecimientos : [];

    esta.innerHTML = '';
    var vacia = document.createElement('option');
    vacia.value = '';
    vacia.textContent = datos ? 'Seleccione el establecimiento…' : 'Seleccione primero la red…';
    esta.appendChild(vacia);

    lista.forEach(function (nombre) {
      var op = document.createElement('option');
      op.value = nombre;
      op.textContent = nombre;
      if (nombre === preferido) { op.selected = true; }
      esta.appendChild(op);
    });
  }

  /** Un administrador no lleva establecimiento: los campos se apagan. */
  function aplicarRol() {
    var esOperador = !rol || rol.value === 'operador';

    [].forEach.call(bloques, function (bloque) {
      bloque.hidden = !esOperador;
    });
    red.disabled  = !esOperador;
    esta.disabled = !esOperador;
    red.required  = esOperador;
    esta.required = esOperador;
  }

  red.addEventListener('change', function () { pintarEstablecimientos(''); });
  if (rol) { rol.addEventListener('change', aplicarRol); }

  pintarEstablecimientos(esta.getAttribute('data-seleccionado') || '');
  aplicarRol();
})();
