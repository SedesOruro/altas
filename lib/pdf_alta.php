<?php
/**
 * Generación del PDF del "Formulario de Notificación de Alta Solicitada".
 *
 * Enfoque (Opción A del pliego): PDF construido íntegramente en PHP con
 * FPDF, una librería de un solo archivo que no requiere Composer, ni
 * shell_exec, ni LibreOffice instalado. Funciona en cualquier hosting
 * compartido con PHP.
 *
 * La maquetación reproduce el documento Word original: membrete con el
 * escudo del Departamento de Oruro y la leyenda "SERVICIO DEPARTAMENTAL DE
 * SALUD - ORURO", título centrado en negrita, cinco secciones numeradas con
 * encabezado en negrita, los valores escritos sobre líneas continuas
 * (equivalentes a los "___" del Word), párrafo de declaración justificado y
 * líneas de firma EN BLANCO, porque el documento se imprime y se firma
 * físicamente.
 *
 * Alternativa documentada (Opción B, no activa): si el hosting permitiera
 * shell_exec y tuviera LibreOffice instalado, se podría rellenar la plantilla
 * templates/formato_alta_solicitada.docx con PHPWord\TemplateProcessor y
 * convertirla con:  soffice --headless --convert-to pdf archivo.docx
 * Ver templates/README-opcion-b.md.
 */

require_once __DIR__ . '/fpdf/fpdf.php';

class PdfAltaSolicitada extends FPDF
{
    /** @var string Código de alta impreso en la cabecera de cada página. */
    private $codigoAlta = '';

    /** @var string Ruta del escudo institucional usado en el membrete. */
    private $rutaEscudo = '';

    public function setCodigoAlta($codigo)
    {
        $this->codigoAlta = $codigo;
    }

    public function setEscudo($ruta)
    {
        $this->rutaEscudo = (is_string($ruta) && is_readable($ruta)) ? $ruta : '';
    }

    /**
     * FPDF trabaja con fuentes base en codificación CP1252, mientras que la
     * aplicación maneja UTF-8. Esta conversión preserva tildes y eñes.
     */
    public function tx($texto)
    {
        $texto = (string) $texto;
        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'CP1252//TRANSLIT', $texto);
            if ($convertido !== false) {
                return $convertido;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($texto, 'CP1252', 'UTF-8');
        }
        return utf8_decode($texto);
    }

    /** Ancho útil de la página entre márgenes. */
    public function anchoUtil()
    {
        return $this->w - $this->lMargin - $this->rMargin;
    }

    // -----------------------------------------------------------------
    // Membrete y pie
    // -----------------------------------------------------------------

    /**
     * Membrete institucional: escudo del Departamento de Oruro centrado,
     * la leyenda del SEDES debajo, y el código de alta recuadrado a la
     * derecha. Se repite en todas las páginas.
     */
    public function Header()
    {
        $y = $this->tMargin;

        if ($this->rutaEscudo !== '') {
            $altoEscudo  = 14.5;
            $anchoEscudo = 14;
            $this->Image($this->rutaEscudo, ($this->w - $anchoEscudo) / 2, $y, $anchoEscudo, $altoEscudo);
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetXY($this->lMargin, $y + 15);
        $this->Cell($this->anchoUtil(), 5, $this->tx('SERVICIO DEPARTAMENTAL DE SALUD - ORURO'), 0, 1, 'C');

        if ($this->codigoAlta !== '') {
            $ancho = 50;
            $this->SetXY($this->w - $this->rMargin - $ancho, $y);
            $this->SetFont('Helvetica', '', 7);
            $this->Cell($ancho, 4.2, $this->tx('CÓDIGO DE ALTA'), 'LTR', 2, 'C');
            $this->SetFont('Helvetica', 'B', 11);
            $this->Cell($ancho, 6.5, $this->tx($this->codigoAlta), 'LBR', 0, 'C');
        }

        $yRegla = $y + 21;
        $this->SetDrawColor(120, 120, 120);
        $this->Line($this->lMargin, $yRegla, $this->w - $this->rMargin, $yRegla);
        $this->SetDrawColor(0, 0, 0);

        $this->SetXY($this->lMargin, $yRegla + 3);
    }

    /** Pie: identificación del documento y número de página. */
    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 3.8, $this->tx('Documento generado por el sistema de Alta Solicitada — SEDES Oruro'), 0, 1, 'L');
        $this->Cell(0, 3.8, $this->tx($this->codigoAlta . '   |   Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
    }

    // -----------------------------------------------------------------
    // Bloques de contenido
    // -----------------------------------------------------------------

    /** Encabezado de sección en negrita (ej. "1. Datos del Establecimiento de Salud:"). */
    public function seccion($titulo, $espacioSuperior = 2.5)
    {
        $this->Ln($espacioSuperior);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 5.4, $this->tx($titulo), 0, 1, 'L');
        $this->Ln(0.5);
    }

    /**
     * Escribe un valor sobre una línea continua, encogiendo la fuente hasta
     * 6,5 pt si hace falta para que quepa (y truncando solo en el extremo).
     */
    private function celdaValor($ancho, $altura, $valor, $saltoLinea)
    {
        $tam   = 9.5;
        $texto = ' ' . $this->tx($valor);
        $util  = $ancho - 2;

        $this->SetFont('Helvetica', 'B', $tam);
        while ($tam > 6.5 && $this->GetStringWidth($texto) > $util) {
            $tam -= 0.5;
            $this->SetFont('Helvetica', 'B', $tam);
        }
        if ($this->GetStringWidth($texto) > $util) {
            while ($texto !== '' && $this->GetStringWidth($texto . '...') > $util) {
                $texto = substr($texto, 0, -1);
            }
            $texto .= '...';
        }

        $this->Cell($ancho, $altura, $texto, 'B', $saltoLinea, 'L');
    }

    /**
     * Fila de uno o varios campos con su etiqueta y su valor sobre una línea,
     * repartiendo el ancho sobrante entre ellos.
     *
     * @param array $campos Cada elemento: array(etiqueta, valor[, peso]).
     */
    public function fila(array $campos, $altura = 6.2)
    {
        $this->SetX($this->lMargin);

        $anchoEtiquetas = 0;
        $pesos          = array();
        $pesoTotal      = 0;

        $this->SetFont('Helvetica', '', 9.5);
        foreach ($campos as $i => $campo) {
            $anchoEtiquetas += $this->GetStringWidth($this->tx($campo[0])) + 1.5;
            $peso           = isset($campo[2]) ? (float) $campo[2] : 1;
            $pesos[$i]      = $peso;
            $pesoTotal     += $peso;
        }

        $libre  = max(10, $this->anchoUtil() - $anchoEtiquetas);
        $ultimo = count($campos) - 1;

        foreach ($campos as $i => $campo) {
            $this->SetFont('Helvetica', '', 9.5);
            $anchoEtiqueta = $this->GetStringWidth($this->tx($campo[0])) + 1.5;
            $this->Cell($anchoEtiqueta, $altura, $this->tx($campo[0]), 0, 0, 'L');

            // El último campo llega exactamente al margen derecho.
            $anchoValor = ($i === $ultimo)
                ? $this->w - $this->rMargin - $this->GetX()
                : $libre * $pesos[$i] / $pesoTotal;

            $this->celdaValor($anchoValor, $altura, $campo[1], ($i === $ultimo) ? 1 : 0);
        }

        $this->Ln(0.8);
    }

    /** Párrafo justificado (declaración legal). */
    public function parrafo($texto, $altura = 4.8, $estilo = '', $tam = 9.5)
    {
        $this->SetFont('Helvetica', $estilo, $tam);
        $this->MultiCell(0, $altura, $this->tx($texto), 0, 'J');
    }

    /**
     * Bloque de texto libre dentro de un recuadro con altura mínima, que
     * corresponde al "espacio en blanco extenso" del formato original.
     */
    public function bloqueTextoLibre($texto, $alturaMinima = 26)
    {
        $this->SetFont('Helvetica', '', 9.5);
        $anchoUtil = $this->anchoUtil();

        // Salto de página anticipado si el bloque no entra completo.
        if ($this->GetY() + $alturaMinima > ($this->h - $this->bMargin)) {
            $this->AddPage();
        }

        $xInicio       = $this->lMargin;
        $yInicio       = $this->GetY();
        $paginaInicial = $this->PageNo();

        $this->SetX($xInicio);
        $this->MultiCell($anchoUtil, 4.8, $this->tx($texto), 0, 'L');
        $yFin = $this->GetY();

        // Si el texto fue tan largo que abarcó varias páginas, no se dibuja el
        // recuadro (no tendría un rectángulo único que lo contenga).
        if ($this->PageNo() !== $paginaInicial) {
            $this->Ln(2);
            return;
        }

        $alturaReal = max($alturaMinima, $yFin - $yInicio + 2);
        $this->Rect($xInicio, $yInicio - 1.5, $anchoUtil, $alturaReal);
        $this->SetXY($xInicio, $yInicio - 1.5 + $alturaReal);
        $this->Ln(2);
    }

    /**
     * Fila de firmas EN BLANCO repartidas a lo ancho de la página, con
     * espacio vertical suficiente para firmar y sellar sobre el impreso.
     */
    public function filaFirmas(array $etiquetas, $espacioSuperior = 24)
    {
        $alturaNecesaria = $espacioSuperior + 10;
        if ($this->GetY() + $alturaNecesaria > ($this->h - $this->bMargin)) {
            $this->AddPage();
            $espacioSuperior = 14;
        }

        $this->Ln($espacioSuperior);
        $y       = $this->GetY();
        $cuantas = count($etiquetas);
        $paso    = $this->anchoUtil() / $cuantas;
        $ancho   = $paso - 6;

        $this->SetFont('Helvetica', '', 8);
        foreach ($etiquetas as $i => $etiqueta) {
            $x = $this->lMargin + $i * $paso;
            $this->Line($x, $y, $x + $ancho, $y);
            $this->SetXY($x, $y + 0.8);
            $this->MultiCell($ancho, 3.8, $this->tx($etiqueta), 0, 'L');
        }

        $this->SetXY($this->lMargin, $y + 9);
    }
}

/**
 * Construye el PDF del formulario a partir de un registro de la tabla `altas`.
 *
 * @param array $a Fila de la tabla altas.
 * @return PdfAltaSolicitada
 */
function construir_pdf_alta(array $a)
{
    $fmtFecha = function ($valor) {
        if (!$valor) {
            return '';
        }
        $ts = strtotime($valor);
        return $ts ? date('d/m/Y', $ts) : (string) $valor;
    };
    $fmtHora = function ($valor) {
        if (!$valor) {
            return '';
        }
        $ts = strtotime('1970-01-01 ' . $valor);
        return $ts ? date('H:i', $ts) . ' hrs.' : (string) $valor;
    };

    $unidades = array('anios' => 'años', 'meses' => 'meses', 'dias' => 'días');
    $unidad   = isset($unidades[$a['edad_unidad']]) ? $unidades[$a['edad_unidad']] : 'años';
    $edad     = $a['edad'] . ' ' . $unidad;
    $sexo     = ($a['sexo'] === 'F') ? 'Femenino' : 'Masculino';

    $pdf = new PdfAltaSolicitada('P', 'mm', 'A4');
    $pdf->setCodigoAlta($a['codigo_alta']);
    $pdf->setEscudo(__DIR__ . '/../assets/membrete.png');
    $pdf->SetTitle('Formulario de Notificacion de Alta Solicitada ' . $a['codigo_alta']);
    $pdf->SetAuthor('SEDES Oruro');
    $pdf->SetCreator('Sistema de Alta Solicitada - SEDES Oruro');
    $pdf->SetMargins(18, 12, 18);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // ---- Título ------------------------------------------------------
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->MultiCell(0, 6.5, $pdf->tx('FORMULARIO DE NOTIFICACIÓN DE ALTA SOLICITADA'), 0, 'C');
    $pdf->Ln(1.5);

    // ---- 1. Datos del Establecimiento de Salud ------------------------
    $pdf->seccion('1. Datos del Establecimiento de Salud:', 1);
    $pdf->fila(array(
        array('Nombre del Establecimiento de Salud:', $a['nombre_establecimiento']),
    ));
    $pdf->fila(array(
        array('Red de Salud:', $a['red_salud']),
        array('Municipio:', $a['municipio']),
    ));
    $pdf->fila(array(
        array('Servicio/Unidad:', $a['servicio_unidad']),
    ));

    // ---- 2. Información del Paciente ----------------------------------
    $pdf->seccion('2. Información del Paciente:');
    $pdf->fila(array(
        array('Nombres y Apellidos:', $a['nombre_paciente'], 3),
        array('Edad:', $edad, 1.1),
        array('Sexo:', $sexo, 1.1),
    ));
    $pdf->fila(array(
        array('Número de Historia Clínica:', $a['numero_historia_clinica']),
    ));
    $pdf->fila(array(
        array('Número de Referencia:', isset($a['numero_referencia']) ? $a['numero_referencia'] : ''),
    ));
    $pdf->fila(array(
        array('Domicilio:', $a['domicilio']),
    ));

    // ---- 3. Detalles de la Internación --------------------------------
    $pdf->seccion('3. Detalles de la Internación:');
    $pdf->fila(array(
        array('Fecha de Internación:', $fmtFecha($a['fecha_internacion'])),
        array('Hora de Internación:', $fmtHora($a['hora_internacion'])),
    ));
    $pdf->fila(array(
        array('Diagnósticos de Ingreso:', $a['diagnosticos_ingreso']),
    ));
    $pdf->fila(array(
        array('Fecha de Alta Solicitada:', $fmtFecha($a['fecha_solicitud'])),
        array('Hora de Solicitud:', $fmtHora($a['hora_solicitud'])),
    ));
    $pdf->fila(array(
        array('Diagnósticos de Egreso:', $a['diagnosticos_egreso']),
    ));

    // ---- 4. Declaración de Alta Solicitada ----------------------------
    $pdf->seccion('4. Declaración de Alta Solicitada:');
    $pdf->parrafo(
        'El que suscribe, en pleno uso de sus facultades, solicita la alta voluntaria del '
        . 'establecimiento de salud mencionado, asumiendo la responsabilidad total de las '
        . 'consecuencias que esta decisión pueda tener sobre su salud, tras haber sido '
        . 'informado por el personal médico sobre los riesgos de interrumpir el tratamiento '
        . 'o la observación.'
    );
    $pdf->Ln(1.5);
    $pdf->SetFont('Helvetica', 'B', 9.5);
    $pdf->Cell(0, 5.2, $pdf->tx('Motivo de Alta según Paciente:'), 0, 1, 'L');
    $pdf->Ln(0.5);
    $pdf->bloqueTextoLibre($a['motivo_alta'], 26);

    // ---- 5. Firmas y Fecha --------------------------------------------
    $pdf->seccion('5. Firmas y Fecha:');
    $pdf->fila(array(
        array('Grado de Parentesco:', isset($a['grado_parentesco']) ? $a['grado_parentesco'] : ''),
        array('N° de Cédula de Identidad/Pasaporte:', $a['ci_pasaporte']),
    ));

    // Las firmas NO se capturan digitalmente: quedan en blanco para firmarse
    // a mano sobre el documento impreso.
    $pdf->filaFirmas(array(
        'Firma del Paciente/Representante Legal',
        'Firma y Sello del Médico',
        'Firma Testigo',
    ), 24);

    // ---- Nota importante ----------------------------------------------
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($pdf->GetStringWidth($pdf->tx('Nota Importante:')) + 1, 4.6, $pdf->tx('Nota Importante:'), 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->MultiCell(
        0,
        4.6,
        $pdf->tx(' El alta solicitada es un proceso delicado, el personal médico explique '
            . 'detalladamente los riesgos clínicos al paciente antes de la firma.'),
        0,
        'J'
    );

    return $pdf;
}
