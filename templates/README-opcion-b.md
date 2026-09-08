# Opción B — Generar el PDF desde una plantilla .docx (alternativa opcional)

El sistema genera el PDF con **FPDF** (Opción A, `lib/pdf_alta.php`), que funciona en
cualquier hosting con PHP sin dependencias externas. Esta carpeta existe únicamente
para la alternativa opcional descrita en el pliego.

Si el hosting permite `shell_exec` y tiene **LibreOffice** instalado, se puede obtener
una fidelidad exacta al diseño original de Word:

1. Colocar en esta carpeta el archivo `formato_alta_solicitada.docx`, que es el Word
   original con los espacios de llenado reemplazados por marcadores:

   - `${nombre_establecimiento}`
   - `${servicio_unidad}`
   - `${nombre_paciente}`
   - `${numero_historia_clinica}`
   - `${numero_referencia}`
   - `${domicilio}`
   - `${fecha_internacion}`
   - `${motivo_alta}`
   - `${fecha_solicitud}`
   - `${hora_solicitud}`
   - `${dni_documento}`
   - `${codigo_alta}`

   Las líneas de firma se dejan en blanco, igual que en el original.

2. Instalar PHPWord (requiere Composer):

   ```
   composer require phpoffice/phpword
   ```

3. Rellenar la plantilla y convertirla a PDF:

   ```php
   $plantilla = new \PhpOffice\PhpWord\TemplateProcessor(__DIR__ . '/formato_alta_solicitada.docx');
   $plantilla->setValue('nombre_establecimiento', $alta['nombre_establecimiento']);
   // ... el resto de marcadores ...
   $docx = sys_get_temp_dir() . '/' . $alta['codigo_alta'] . '.docx';
   $plantilla->saveAs($docx);

   $salida = sys_get_temp_dir();
   shell_exec('soffice --headless --convert-to pdf --outdir '
       . escapeshellarg($salida) . ' ' . escapeshellarg($docx));
   ```

**Advertencia:** la mayoría de los hostings compartidos económicos deshabilitan
`shell_exec` y no tienen LibreOffice. Por eso la Opción A es la implementación activa
y esta queda documentada como alternativa.
