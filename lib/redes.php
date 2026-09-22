<?php
/**
 * Catálogo de redes de salud del departamento de Oruro.
 *
 * Es la única fuente de verdad: de aquí salen las opciones que se pintan en
 * el formulario, el JSON que usa el navegador para el llenado automático y
 * las comprobaciones que hace el servidor al guardar. Si mañana se agrega un
 * establecimiento, se cambia solo este archivo.
 *
 * Cada red fija su municipio y la lista de establecimientos que le
 * corresponden. Cuando la lista tiene un solo establecimiento, el formulario
 * lo selecciona solo.
 */

/** @return array Catálogo completo: red => array('municipio', 'establecimientos') */
function catalogo_redes()
{
    return array(
        'Red Urbana' => array(
            'municipio'        => 'Oruro',
            'establecimientos' => array(
                'Hospital General San Juan de Dios de Oruro',
                'Hospital Walter Khon',
                'Hospital Barrios Mineros',
                'C.S.I. 7 de Marzo',
                'C.S.I. Rafael Pabón',
                'C.S.I. Rumy Campana',
                'C.S.I. Vinto',
            ),
        ),
        'Red Azanake' => array(
            'municipio'        => 'Challapata',
            'establecimientos' => array(
                'Hospital San Juan de Dios de Challapata',
            ),
        ),
        'Red Minera' => array(
            'municipio'        => 'Huanuni',
            'establecimientos' => array(
                'Hospital San Martín de Porres',
            ),
        ),
        'Red Norte' => array(
            'municipio'        => 'Caracollo',
            'establecimientos' => array(
                'Hospital San Andrés de Caracollo',
            ),
        ),
    );
}

/** ¿Existe esa red en el catálogo? */
function red_valida($red)
{
    return is_string($red) && array_key_exists($red, catalogo_redes());
}

/** Municipio que corresponde a una red, o '' si la red no existe. */
function municipio_de_red($red)
{
    $catalogo = catalogo_redes();
    return isset($catalogo[$red]) ? $catalogo[$red]['municipio'] : '';
}

/** ¿Ese establecimiento pertenece a esa red? */
function establecimiento_de_red($red, $establecimiento)
{
    $catalogo = catalogo_redes();
    return isset($catalogo[$red])
        && in_array($establecimiento, $catalogo[$red]['establecimientos'], true);
}

/** El catálogo en JSON, para incrustarlo en la página. */
function catalogo_redes_json()
{
    return json_encode(catalogo_redes(), JSON_UNESCAPED_UNICODE);
}
