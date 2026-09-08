<?php
/**
 * Generación del PDF del "Formato de Notificación de Alta Solicitada".
 *
 * Enfoque (Opción A del pliego): PDF construido íntegramente en PHP con
 * FPDF, una librería de un solo archivo que no requiere Composer, ni
 * shell_exec, ni LibreOffice instalado. Funciona en cualquier hosting
 * compartido con PHP.
 *
 * La maquetación reproduce el documento Word original: título centrado en
 * negrita, cinco secciones numeradas con encabezado en negrita, los valores
 * escritos sobre líneas continuas (equivalentes a los "___" del Word),
 * párrafo de declaración justificado y líneas de firma EN BLANCO, porque el
 * documento se imprime y se firma físicamente.
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

    public function setCodigoAlta($codigo)
    {
        $this->codigoAlta = $codigo;
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

    /** Cabecera: el código de alta, visible en la esquina superior derecha. */
    public function Header()
    {
        if ($this->codigoAlta === '') {
            return;
        }
        $yInicial = $this->GetY();
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);

        $ancho = 62;
        $x     = $this->w - $this->rMargin - $ancho;
        $this->SetXY($x, $this->tMargin);
        $this->SetFont('Helvetica', '', 7.5);
        $this->Cell($ancho, 4.5, $this->tx('CÓDIGO DE ALTA'), 'LTR', 2, 'C');
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell($ancho, 7, $this->tx($this->codigoAlta), 'LBR', 0, 'C');

        $this->SetXY($this->lMargin, $yInicial + 12);
    }

    /** Pie: identificación del documento y número de página. */
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 4, $this->tx('Documento generado por el sistema de Alta Solicitada — SEDES Oruro'), 0, 1, 'L');
        $this->Cell(0, 4, $this->tx($this->codigoAlta . '   |   Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
    }

    /** Encabezado de sección en negrita (ej. "1. Datos del Establecimiento de Salud:"). */
    public function seccion($titulo, $espacioSuperior = 3)
    {
        $this->Ln($espacioSuperior);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->Cell(0, 5.6, $this->tx($titulo), 0, 1, 'L');
        $this->Ln(0.5);
    }

    /**
     * Línea de campo: etiqueta seguida del valor escrito sobre una línea
     * continua que llega hasta el margen derecho (equivale a los guiones
     * bajos del documento Word).
     *
     * @param string $etiqueta Texto de la etiqueta, incluido el ":".
     * @param string $valor    Valor a imprimir sobre la línea ('' = línea en blanco).
     */
    public function campo($etiqueta, $valor = '', $altura = 6.6)
    {
        $this->SetFont('Helvetica', '', 10);
        $anchoEtiqueta = $this->GetStringWidth($this->tx($etiqueta)) + 1.5;
        $this->Cell($anchoEtiqueta, $altura, $this->tx($etiqueta), 0, 0, 'L');

        $anchoLinea = $this->w - $this->rMargin - $this->GetX();
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell($anchoLinea, $altura, ' ' . $this->tx($valor), 'B', 1, 'L');
        $this->Ln(1);
    }

    /** Línea con dos campos: usada para "Fecha y Hora de la Solicitud". */
    public function campoDoble($etiqueta, $valor1, $separador, $valor2, $anchoValor1 = 55, $altura = 6.6)
    {
        $this->SetFont('Helvetica', '', 10);
        $this->Cell($this->GetStringWidth($this->tx($etiqueta)) + 1.5, $altura, $this->tx($etiqueta), 0, 0, 'L');

        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell($anchoValor1, $altura, ' ' . $this->tx($valor1), 'B', 0, 'L');

        $this->SetFont('Helvetica', '', 10);
        $this->Cell($this->GetStringWidth($this->tx($separador)) + 3, $altura, ' ' . $this->tx($separador), 0, 0, 'L');

        $anchoRestante = $this->w - $this->rMargin - $this->GetX();
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell($anchoRestante, $altura, ' ' . $this->tx($valor2), 'B', 1, 'L');
        $this->Ln(1);
    }

    /** Párrafo justificado (declaración legal). */
    public function parrafo($texto, $altura = 5, $estilo = '', $tam = 10)
    {
        $this->SetFont('Helvetica', $estilo, $tam);
        $this->MultiCell(0, $altura, $this->tx($texto), 0, 'J');
    }

    /**
     * Bloque de texto libre dentro de un recuadro con altura mínima, que
     * corresponde al "espacio en blanco extenso" del formato original.
     */
    public function bloqueTextoLibre($texto, $alturaMinima = 30)
    {
        $this->SetFont('Helvetica', '', 10);
        $anchoUtil = $this->w - $this->lMargin - $this->rMargin;

        // Salto de página anticipado si el bloque no entra completo.
        if ($this->GetY() + $alturaMinima > ($this->h - $this->bMargin)) {
            $this->AddPage();
        }

        $xInicio       = $this->GetX();
        $yInicio       = $this->GetY();
        $paginaInicial = $this->PageNo();

        $this->MultiCell($anchoUtil, 5, $this->tx($texto), 0, 'L');
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
     * Línea de firma EN BLANCO, con espacio vertical suficiente para firmar
     * a mano sobre el documento impreso.
     */
    public function lineaFirma($etiqueta, $espacioSuperior = 12, $anchoLinea = 95)
    {
        if ($this->GetY() + $espacioSuperior + 8 > ($this->h - $this->bMargin)) {
            $this->AddPage();
            $espacioSuperior = 6;
        }
        $this->Ln($espacioSuperior);
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Line($x, $y, $x + $anchoLinea, $y);
        $this->SetXY($x, $y + 0.8);
        $this->SetFont('Helvetica', '', 9);
        $this->Cell($anchoLinea, 5, $this->tx($etiqueta), 0, 1, 'L');
    }
}

/**
 * Construye el PDF del formato a partir de un registro de la tabla `altas`.
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
        return $ts ? date('H:i', $ts) : (string) $valor;
    };

    $pdf = new PdfAltaSolicitada('P', 'mm', 'A4');
    $pdf->setCodigoAlta($a['codigo_alta']);
    $pdf->SetTitle('Formato de Notificacion de Alta Solicitada ' . $a['codigo_alta']);
    $pdf->SetAuthor('SEDES Oruro');
    $pdf->SetCreator('Sistema de Alta Solicitada - SEDES Oruro');
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // ---- Título ------------------------------------------------------
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->MultiCell(0, 7, $pdf->tx('FORMATO DE NOTIFICACIÓN DE ALTA SOLICITADA'), 0, 'C');
    $pdf->Ln(2);

    // ---- 1. Datos del Establecimiento de Salud ------------------------
    $pdf->seccion('1. Datos del Establecimiento de Salud:', 1);
    $pdf->campo('Nombre del Establecimiento de Salud:', $a['nombre_establecimiento']);
    $pdf->campo('Servicio/Unidad:', $a['servicio_unidad']);

    // ---- 2. Información del Paciente ----------------------------------
    $pdf->seccion('2. Información del Paciente:');
    $pdf->campo('Nombres y Apellidos:', $a['nombre_paciente']);
    $pdf->campo('Número de Historia Clínica:', $a['numero_historia_clinica']);
    $pdf->campo('Número de Referencia:', isset($a['numero_referencia']) ? $a['numero_referencia'] : '');
    $pdf->campo('Domicilio:', $a['domicilio']);

    // ---- 3. Detalles de la Internación --------------------------------
    $pdf->seccion('3. Detalles de la Internación:');
    $pdf->campo('Fecha de Internación:', $fmtFecha($a['fecha_internacion']));

    // ---- 4. Declaración de Alta Solicitada ----------------------------
    $pdf->seccion('4. Declaración de Alta Solicitada:');
    $pdf->parrafo(
        'El que suscribe, en pleno uso de sus facultades, solicita la alta voluntaria del '
        . 'establecimiento de salud mencionado, asumiendo la responsabilidad total de las '
        . 'consecuencias que esta decisión pueda tener sobre su salud, tras haber sido '
        . 'informado por el personal médico sobre los riesgos de interrumpir el tratamiento '
        . 'o la observación.'
    );
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5.6, $pdf->tx('Motivo de Alta según Paciente:'), 0, 1, 'L');
    $pdf->Ln(0.5);
    $pdf->bloqueTextoLibre($a['motivo_alta'], 30);

    // ---- 5. Firmas y Fecha --------------------------------------------
    $pdf->seccion('5. Firmas y Fecha:');
    $pdf->campoDoble(
        'Fecha y Hora de la Solicitud:',
        $fmtFecha($a['fecha_solicitud']),
        'hrs.:',
        $fmtHora($a['hora_solicitud']),
        52
    );

    // Las firmas NO se capturan digitalmente: quedan en blanco para firmarse
    // a mano sobre el documento impreso.
    $pdf->lineaFirma('Firma del Paciente/Representante Legal', 11);
    $pdf->Ln(2);
    $pdf->campo('DNI/Documento de Identidad:', $a['dni_documento']);
    $pdf->lineaFirma('Firma y Sello del Médico Tratante/Testigo', 12);

    // ---- Nota importante ----------------------------------------------
    $pdf->Ln(6);
    $pdf->SetFont('Helvetica', 'B', 9.5);
    $pdf->Cell($pdf->GetStringWidth($pdf->tx('Nota Importante:')) + 1, 5, $pdf->tx('Nota Importante:'), 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->MultiCell(
        0,
        5,
        $pdf->tx(' El alta solicitada es un proceso delicado, el personal médico explique '
            . 'detalladamente los riesgos clínicos al paciente antes de la firma.'),
        0,
        'J'
    );

    return $pdf;
}
