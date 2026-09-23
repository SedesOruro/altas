# Sistema Web «Alta Solicitada» — SEDES Oruro

Digitalización del **Formulario de Notificación de Alta Solicitada** (alta voluntaria de un
paciente que decide retirarse de un establecimiento de salud bajo su propia responsabilidad).

El PDF reproduce el formulario oficial con su **membrete institucional**: el escudo del
Departamento de Oruro y la leyenda «SERVICIO DEPARTAMENTAL DE SALUD - ORURO».

El sistema tiene dos caras:

**Pública** — `index.html` es la portada de presentación, con el botón **«Registro de Altas»**
que lleva al formulario (`registro_altas.html`), donde el personal de salud:

1. **Llena la información** — el sistema asigna un **código de alta correlativo único**
   (`ALTA-000001`) y genera el **PDF** con el formato oficial, listo para imprimir y firmar
   en físico.
2. **Sube el alta firmada** — se carga el documento ya firmado (PDF o JPG). El sistema valida
   que el código corresponda a una solicitud registrada antes de aceptar el archivo, y marca
   el registro como `verificado`.

**Privada** — el botón **«Inicio de Sesión»** de la portada abre el panel de administración
(AdminLTE), con el listado completo de altas —filtros, paginación y acceso a ambos PDF de
cada registro— y la gestión de los usuarios que pueden entrar.

---

## Tecnología

**PHP + MySQL, sin backend separado.** No hay Node.js, ni `npm install`, ni ningún proceso
que mantener corriendo aparte del propio servidor web. El navegador llama a los archivos
`.php` con `fetch()`, y el servidor web (Apache/Nginx) los interpreta al vuelo — exactamente
como funciona cualquier hosting compartido con cPanel.

PHP es necesario porque el navegador no puede conectarse directamente a MySQL por seguridad:
estos scripts son ese puente, nada más.

| Componente | Elección | Motivo |
|---|---|---|
| Base de datos | MySQL (PDO, sentencias preparadas) | Estándar en cualquier hosting |
| PDF | [FPDF](http://www.fpdf.org) 1.86, incluido en `lib/fpdf/` | Un solo archivo, sin Composer, sin `shell_exec`, sin LibreOffice |
| Frontend | HTML + CSS + JavaScript sin frameworks | Sin build, se sube tal cual |

| Panel | [AdminLTE 3.2](https://adminlte.io) + Bootstrap 4, incluidos en `assets/vendor/` | Sin CDN: funciona en una intranet sin salida a internet |

**Requisitos del servidor:** PHP 7.3 o superior (probado en 7.4 y 8.3) con las extensiones
`pdo_mysql`, `fileinfo` y `mbstring` (o `iconv`), y acceso a un servidor MySQL 5.7+ / MariaDB.

> PHP 7.3 es el mínimo por las cookies de sesión del panel (`SameSite`). El formulario
> público por sí solo funciona desde PHP 5.6.

---

## Instalación

> **¿Va a desplegar en CapRover?** Siga [`DEPLOY.md`](DEPLOY.md), que cubre la imagen Docker,
> los directorios persistentes para los documentos subidos, las variables de entorno, el
> dominio `https://altas.app.sedesoruro.gob.bo` y los respaldos. Esta sección describe la
> instalación en un hosting compartido clásico (cPanel, FTP).

### 1. Crear la base de datos e importar el esquema

Desde phpMyAdmin: crear una base de datos (por ejemplo `sedes_altas`) con cotejamiento
`utf8mb4_unicode_ci`, entrar en ella y usar **Importar** con el archivo `schema.sql`.

Desde consola:

```bash
mysql -u USUARIO -p -e "CREATE DATABASE sedes_altas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u USUARIO -p sedes_altas < schema.sql
```

Esto crea las tablas `altas` y `alta_adjuntos`.

**Si ya tenía instalada la versión anterior del sistema**, no ejecute `schema.sql`: use la
migración, que conserva los registros existentes y añade los campos del formulario nuevo
(Red de salud, Municipio, Edad, Sexo, Hora de internación, Diagnósticos de ingreso y egreso,
Grado de parentesco, y el cambio de «DNI/Documento» a «Cédula de Identidad/Pasaporte»):

```bash
mysqldump -u USUARIO -p sedes_altas > respaldo_antes_de_migrar.sql
mysql -u USUARIO -p sedes_altas < migracion_v1_a_v2.sql   # campos del formulario nuevo
mysql -u USUARIO -p sedes_altas < migracion_v2_a_v3.sql   # tabla de usuarios del panel
mysql -u USUARIO -p sedes_altas < migracion_v3_a_v4.sql   # datos de quien firma el alta
```

Las filas anteriores quedan con valores provisionales visibles (`(no registrado)`, edad `0`)
en los campos que antes no existían; corríjalos a mano si esos registros aún se usan.

Si ya tenía la versión 2 instalada, bastan `migracion_v2_a_v3.sql` y `migracion_v3_a_v4.sql`;
si venía de la 3, solo esta última.

### 2. Configurar la conexión

```bash
cp config.example.php config.local.php
```

Editar `config.local.php` con los datos reales del servidor MySQL:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sedes_altas');
define('DB_USER', 'usuario_mysql');
define('DB_PASS', 'clave_mysql');
define('APP_DEBUG', false);   // false en producción
```

`config.local.php` **no se versiona** (está en `.gitignore`). Si el hosting permite variables
de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_DEBUG`,
`APP_TIMEZONE`), estas tienen prioridad y el archivo no es necesario — así funciona el
despliegue en CapRover.

### 3. Subir los archivos

Subir toda la carpeta al hosting (FTP, Administrador de archivos de cPanel, git...) y apuntar
el dominio o subdominio a ella. No hay nada que compilar ni que arrancar.

**Permisos:** la carpeta `uploads/altas/` debe tener permiso de escritura para el usuario del
servidor web (normalmente `755`, o `775` si el propietario difiere). La carpeta `data/`
(usada por el limitador de intentos) se crea sola con los mismos requisitos.

### 4. Crear el primer usuario del panel

Abrir `registro.php` en el navegador y completar el formulario. **Esa página solo está
abierta mientras no exista ningún usuario**: creado el primero, exige haber iniciado sesión,
de modo que las cuentas posteriores las dan de alta quienes ya tienen acceso.

Es deliberado: el panel muestra datos clínicos identificables, y un registro público
equivaldría a repartir las llaves. Si en su caso prefiere un registro abierto, quite la
comprobación de `$registroAbierto` en `registro.php`.

### 5. Comprobación

Abrir la página, registrar una solicitud de prueba, descargar el PDF y subir ese mismo PDF en
la sección «Subir alta firmada». Entrar luego al panel y comprobar que la fila aparece con sus
dos botones de PDF. Después, borrar el registro de prueba:

```sql
DELETE FROM alta_adjuntos; DELETE FROM altas; ALTER TABLE altas AUTO_INCREMENT = 1;
```

---

## Estructura de archivos

```
index.html                 Portada pública de presentación
registro_altas.php         Formulario de altas (llenado + subida del firmado)
login.php                  Inicio de sesión del panel
registro.php               Alta de usuarios del panel
salir.php                  Cierre de sesión

admin/index.php            Panel: tabla de altas con filtros y paginación
admin/usuarios.php         Panel: listado, activación y borrado de usuarios
admin/usuario_editar.php   Panel: edición de un usuario y borrado con confirmación
admin/listar_altas.php     GET   → JSON con las altas filtradas y paginadas
admin/ver_adjunto.php      GET   → entrega el documento firmado (exige sesión)
admin/_plantilla.php       Armazón AdminLTE común a las páginas del panel

assets/style.css           Estilos
assets/app.js              Validación en cliente y llamadas fetch()
assets/calendario.js       Selector de fecha táctil que sustituye al campo nativo
assets/tipografia.css      Declaraciones @font-face de Atkinson Hyperlegible
assets/fonts/              Archivos .woff2 de la tipografía (auto-alojada)
assets/salud.svg           Ilustración de fondo de la portada
assets/admin.css           Ajustes propios sobre AdminLTE
assets/admin.js            Tabla del panel: filtros, paginación y botones
assets/vendor/             AdminLTE 3.2, Bootstrap 4 y jQuery (sin CDN)
assets/membrete.png        Escudo del Departamento de Oruro (membrete del PDF y de la web)

config.php                 Conexión PDO + constantes de la aplicación
config.example.php         Plantilla de configuración (sin credenciales)
config.local.php           Credenciales reales — NO versionar

crear_alta.php             POST  → valida, guarda y genera el código correlativo
generar_pdf.php            GET   → devuelve el PDF del formato
verificar_codigo.php       GET   → informa si un código de alta existe
subir_adjunto.php          POST  → recibe y registra el documento firmado

lib/auth.php               Sesiones, contraseñas, CSRF y guardias del panel
lib/redes.php              Catálogo de redes, municipios y establecimientos
lib/helpers.php            Respuestas JSON, validación, limitador de intentos
lib/pdf_alta.php           Maquetación del PDF (fidelidad al formato Word)
lib/fpdf/                  Librería FPDF 1.86 (incluida, sin Composer)

schema.sql                 Creación de las tablas MySQL (instalación nueva)
migracion_v1_a_v2.sql      Actualización de la v1 al formulario nuevo
migracion_v2_a_v3.sql      Agrega la tabla de usuarios del panel
migracion_v3_a_v4.sql      Agrega los datos de quien firma el alta

DEPLOY.md                  Guía de despliegue en CapRover
Dockerfile                 Imagen Apache + mod_php para el despliegue en contenedor
captain-definition         Archivo que CapRover usa para construir la imagen
docker/apache-altas.conf   Configuración y reglas de seguridad de Apache
docker/php-altas.ini       Límites de subida, zona horaria y manejo de errores
docker/entrypoint.sh       Ajusta permisos de los volúmenes antes de arrancar Apache
uploads/altas/             Documentos firmados subidos (+ .htaccess de protección)
data/                      Contadores del limitador de intentos (se crea sola)
templates/                 Documentación de la Opción B (plantilla .docx)
```

---

## Interfaz

La página está diseñada para móvil primero y se adapta a tableta y escritorio: una sola
columna hasta 719 px y dos columnas desde 720 px, con controles de al menos 44 px de alto y
texto de 16 px en los campos (por debajo de ese tamaño iOS hace zoom automático al escribir).

### Sistema de diseño

Los colores, espacios, tamaños y sombras salen de un juego de fichas (*tokens*) declarado en
`:root`, al principio de `assets/style.css`. Los componentes nunca escriben un color en
crudo: usan los alias semánticos (`--color-marca`, `--color-error`, `--e-4`, `--radio`…), de
modo que un cambio de identidad se hace en un solo lugar. El panel comparte esas decisiones
en `assets/admin.css`.

La identidad es un **terracota cálido** (`--marca-700` = `#a8470e`). Los tonos de marca son
quemados a propósito: un naranja vivo sobre blanco no llega al contraste 4.5:1 que exige la
WCAG AA, mientras que este alcanza 5.9:1 con texto blanco y 7.2:1 en su variante oscura. Los
grises llevan algo de calidez, porque un gris frío junto al terracota se ve sucio.

El **anillo de foco no es naranja** sino casi negro (`#22160f`): sobre un botón de marca, un
anillo del mismo color desaparecería.

Los botones usan esquinas suaves (14 px, no cápsula: la cápsula resta seriedad a un trámite),
un degradado de un solo paso, sombra en capas y una elevación de 1 px al pasar por encima;
al pulsarlos se hunden, que es lo que confirma el toque en pantalla táctil.

La portada lleva de fondo `assets/salud.svg`, una ilustración propia de motivos de salud
—pulso, escudo, cruz y estetoscopio—. Es un SVG y no una fotografía: pesa unos pocos
kilobytes, se ve nítido en cualquier pantalla, no arrastra licencias de terceros y se tiñe
con los colores del sistema. Va en un pseudoelemento con opacidad baja y un velo claro
encima, de modo que es decorativa (no aparece en el árbol de accesibilidad) y el titular
conserva su contraste.

La tipografía es **Atkinson Hyperlegible**, del Braille Institute, alojada en el propio
servidor (`assets/fonts/`, ~80 KB). Está diseñada para distinguir caracteres que suelen
confundirse —I/l/1, O/0, b/d—, lo que importa cuando lo que se lee son números de historia
clínica y cédulas. Como el resto de dependencias, no se carga desde una CDN.

### Accesibilidad

- Contraste comprobado en las cuatro pantallas: todo el texto llega a 4.5:1 (3:1 en títulos
  grandes). Se corrigieron los colores de AdminLTE que no llegaban —el verde de las insignias
  daba 3.1:1 y el azul del menú activo 3.98:1—, y las tarjetas de resumen se rehicieron
  porque el texto blanco sobre su amarillo daba 1.9:1.
- Un único anillo de foco visible en toda la aplicación, nunca suprimido.
- Enlace «Saltar al contenido» al principio de cada página pública.
- Cada campo enlaza con su ayuda y su error mediante `aria-describedby`, y se marca con
  `aria-invalid` cuando falla.
- El error nunca se comunica solo con color: hay borde reforzado, icono y texto.
- Objetivos táctiles de 44 px o más, y `prefers-reduced-motion` respetado.

### Formulario largo

Cinco secciones son muchas para no saber por dónde se va, así que el formulario añade:

- Una **barra de progreso** fija que indica la sección pendiente y cuántas están completas;
  cada sección terminada marca su número en verde.
- **Validación al salir del campo**, no mientras se escribe: se avisa de un campo obligatorio
  vacío solo después de haberlo visitado, y el error desaparece en cuanto se corrige.
- Un **resumen de errores** al fallar el envío, que recibe el foco y enlaza con cada campo
  con problema. Complementa los mensajes por campo, no los sustituye, y se descuenta a medida
  que se corrigen.

Los campos de fecha **no usan el selector nativo del navegador**, cuyo comportamiento en móvil
cambia mucho de un equipo a otro. `assets/calendario.js` los convierte en un botón que muestra
la fecha en formato `dd/mm/aaaa` y abre un calendario propio con selectores de mes y año,
cuadrícula de días táctil y atajos «Hoy» y «Ayer». El `<input>` original se conserva como
`hidden`, así que el envío y la validación no cambian.

### Redes, municipios y establecimientos

La primera sección del formulario es dependiente: al elegir la **Red de Salud** se completa
solo el **Municipio** y la lista de **Establecimientos** se acota a los de esa red. Cuando la
red tiene un único establecimiento, queda seleccionado sin intervención del usuario.

| Red | Municipio | Establecimientos |
|---|---|---|
| Red Urbana | Oruro | Hospital General San Juan de Dios de Oruro, Hospital Walter Khon, Hospital Barrios Mineros, C.S.I. 7 de Marzo, C.S.I. Rafael Pabón, C.S.I. Rumy Campana, C.S.I. Vinto |
| Red Azanake | Challapata | Hospital San Juan de Dios de Challapata |
| Red Minera | Huanuni | Hospital San Martín de Porres |
| Red Norte | Caracollo | Hospital San Andrés de Caracollo |

El catálogo vive en **[`lib/redes.php`](lib/redes.php)** y es la única fuente: de ahí salen
las opciones que pinta `registro_altas.php`, el JSON que usa el navegador para el llenado
automático, y las comprobaciones que hace `crear_alta.php` al guardar. Para agregar o quitar
un establecimiento basta con editar ese archivo.

La validación también es del lado del servidor, no solo del navegador: una petición con una
red inexistente, o con un establecimiento que no pertenece a la red enviada, se rechaza con
`400`. El municipio ni siquiera se toma del cliente — se deriva de la red, de modo que no
pueden guardarse combinaciones imposibles.

> **Al publicar cambios en el CSS o el JavaScript**, suba el número de versión de
> `?v=` en las tres referencias de `index.html`. Sin eso, los navegadores que ya visitaron
> la página pueden seguir usando la copia guardada en caché.

---

## Panel de administración

Se entra desde el botón **«Inicio de Sesión»** de la portada. Está construido con
**AdminLTE 3**, con sus archivos incluidos en `assets/vendor/`: no se carga nada desde una
CDN, así que el panel funciona igual en una intranet sin salida a internet. Los iconos son
SVG en línea, para no arrastrar una tipografía de iconos entera.

**Altas registradas** muestra la tabla con todos los campos del formulario. Tiene búsqueda
por código, paciente, historia clínica o cédula; filtros por estado y por rango de fechas de
alta; y paginación de 10 a 100 filas. La columna **Opciones** queda fija al desplazarse en
horizontal y trae dos botones por fila:

- **PDF registrado** — el documento que generó el formulario.
- **PDF subido** — el escaneo firmado que se cargó después; aparece deshabilitado mientras
  no exista.

**Usuarios** lista las cuentas con tres operaciones sobre cada una:

- **Editar** (`admin/usuario_editar.php`) — los mismos cinco campos del registro más un
  cambio de contraseña opcional: dejar esos dos campos vacíos conserva la actual, que es lo
  que se espera cuando lo que se viene a corregir es un teléfono. La validación es la misma
  función que usa el registro (`validar_datos_usuario`), así que las dos pantallas no pueden
  divergir.
- **Desactivar / Activar** — retira o devuelve el acceso conservando el registro de quién
  entró y cuándo. Es la vía recomendada.
- **Eliminar** — borrado definitivo, con confirmación en el navegador y dos candados en el
  servidor: **nadie puede borrarse a sí mismo** (cerraría su propia sesión a mitad de la
  operación) ni **dejar la tabla de usuarios vacía**, porque `registro.php` se abre al
  público cuando no hay ningún usuario y eso dejaría el registro a merced de cualquiera.

Todas las acciones van por POST con testigo anti-CSRF y responden con una redirección, para
que al recargar la página no se repita la operación.

Una cuenta eliminada o desactivada **pierde el acceso en el acto**: `exigir_sesion()`
comprueba en cada petición que el usuario de la sesión siga existiendo y activo, y si no,
cierra la sesión y avisa en el login. Sin eso, la cookie sobreviviría al borrado y la persona
seguiría dentro del panel hasta cerrar el navegador.

### Seguridad del panel

- Contraseñas con `password_hash()` (bcrypt); en la base nunca hay texto plano.
- Cookie de sesión `HttpOnly` y `SameSite=Lax`, y `Secure` cuando la visita llega por HTTPS
  (se detecta también detrás del proxy de CapRover, por `X-Forwarded-Proto`).
- El identificador de sesión se regenera al autenticarse, contra la fijación de sesión.
- Testigo anti-CSRF en los formularios de login, registro y cambios de estado.
- Límite de intentos por IP en el login y en el registro.
- Un login fallido responde siempre lo mismo, exista o no el usuario.
- **Los documentos firmados no se sirven desde una URL pública.** La carpeta `uploads/` está
  denegada por HTTP y los archivos se entregan únicamente desde `admin/ver_adjunto.php`,
  que exige sesión. PHP los lee del disco, así que la restricción no afecta al panel.

---

## Scripts PHP («endpoints»)

Todos responden en JSON, salvo `generar_pdf.php` que devuelve el binario del PDF.

### `crear_alta.php` — POST

Acepta JSON o formulario.

| Sección | Campos |
|---|---|
| 1. Establecimiento | `nombre_establecimiento`, `red_salud`, `municipio`, `servicio_unidad` |
| 2. Paciente | `nombre_paciente`, `edad` (entero), `edad_unidad` (`anios`/`meses`/`dias`), `sexo` (`M`/`F`), `numero_historia_clinica`, `numero_referencia` *(opcional)*, `domicilio` |
| 3. Internación | `fecha_internacion` (AAAA-MM-DD), `hora_internacion` (HH:MM), `diagnosticos_ingreso`, `fecha_solicitud`, `hora_solicitud`, `diagnosticos_egreso` |
| 4. Declaración | `motivo_alta` |
| 5. Firmas | `grado_parentesco` *(opcional)*, `nombre_firmante`, `ci_pasaporte`, `telefono_firmante` *(opcional)*, `direccion_firmante` *(opcional)* |

El servidor rechaza un alta cuya fecha y hora sean anteriores a las de la internación.

- `201` → `{ok, codigo_alta, registro, pdf_url}`
- `400` → `{ok:false, error, campos:{campo: mensaje}}`
- `500` → error de servidor (el detalle queda en el log, no se expone al cliente)

### `generar_pdf.php?codigo=ALTA-000001` — GET

Devuelve el PDF como descarga. Con `&modo=inline` lo abre en el navegador.
`404` si el código no existe.

### `verificar_codigo.php?codigo=ALTA-000001` — GET

`200` → `{ok, codigo_alta, paciente, servicio, estado, adjuntos}` · `404` si no existe.
Devuelve solo lo necesario para confirmar el registro, no los datos clínicos completos.

### `subir_adjunto.php` — POST `multipart/form-data`

Campos: `codigo_alta` y `archivo` (PDF o JPG, máximo 10 MB).

- `201` → documento guardado y registro marcado como `verificado`
- `400` → código inválido, sin archivo, o tipo no permitido
- `404` → el código no corresponde a ninguna solicitud registrada
- `409` → esa alta ya tiene un documento firmado registrado
- `413` → el archivo supera el tamaño máximo

---

## Código de alta correlativo

El código se deriva del `AUTO_INCREMENT` de la tabla: dentro de una misma transacción se
inserta la fila y acto seguido se ejecuta

```sql
UPDATE altas SET codigo_alta = CONCAT('ALTA-', LPAD(id, 6, '0')) WHERE id = ?
```

Al depender del `id`, el código es **único por construcción** y no puede duplicarse aunque
lleguen solicitudes simultáneas. La numeración es continua y no se reinicia.

---

## El PDF

`lib/pdf_alta.php` reproduce el documento Word original: membrete con el escudo del
Departamento de Oruro y la leyenda del SEDES, título centrado en negrita, las cinco secciones
numeradas con encabezado en negrita, los valores escritos sobre líneas continuas
(equivalentes a los `___` del Word), el párrafo de declaración justificado y la nota final.
El código de alta aparece recuadrado en la esquina superior derecha de cada página.

Con datos de longitud normal el formulario entra en **una sola página**. Los valores que no
caben en su línea reducen el tamaño de letra automáticamente antes de recortarse.

**Las firmas quedan en blanco a propósito.** El formulario se imprime y se firma a mano; por
eso «Firma del Paciente/Representante Legal», «Firma y Sello del Médico» y «Firma Testigo»
son tres líneas vacías con espacio suficiente para firmar y sellar. La versión firmada se
digitaliza y se sube en la Sección B.

### Alternativa (Opción B)

Si el hosting permitiera `shell_exec` y tuviera LibreOffice instalado, se podría rellenar una
plantilla `.docx` con PHPWord y convertirla con `soffice --headless --convert-to pdf`, para
una fidelidad exacta al diseño de Word. Está documentado en
[`templates/README-opcion-b.md`](templates/README-opcion-b.md), pero **no está activo**:
la mayoría de los hostings compartidos no lo permiten.

---

## Seguridad

- **SQL:** todas las consultas usan sentencias preparadas PDO con parámetros. Nunca se
  concatenan variables en el SQL.
- **Archivos subidos:** se valida el **tipo MIME real** del contenido con `finfo_file()`, no
  la extensión ni el `Content-Type` que envía el navegador (ambos falseables). Solo se
  aceptan `application/pdf` e `image/jpeg`.
- **Nombre en disco:** se construye desde cero (`ALTA-000001_20260907_154012_a1b2c3d4.pdf`),
  nunca se usa el nombre enviado por el cliente, lo que descarta *path traversal*. El nombre
  original se guarda solo como dato informativo, ya saneado.
- **Ejecución en `uploads/`:** un `.htaccess` desactiva el motor PHP, quita los handlers de
  script y bloquea el listado de directorio, de modo que un archivo malicioso disfrazado de
  PDF no puede ejecutarse.
- **Tamaño:** límite de 10 MB por archivo. Debe estar acompañado en `php.ini` por
  `upload_max_filesize` y `post_max_size` iguales o mayores.
- **Fuerza bruta:** `verificar_codigo.php` y `subir_adjunto.php` aplican un límite de intentos
  por IP (archivos de contador en `data/`), para que no se puedan barrer códigos de alta.
- **Errores:** con `APP_DEBUG` en `false` no se muestra ningún detalle interno de PHP o MySQL
  al cliente; todo se registra en el log de errores del servidor.
- **Validación:** la validación de JavaScript es solo comodidad; el servidor vuelve a validar
  todos los campos de forma independiente.

### Nota sobre Nginx

El `.htaccess` de `uploads/` solo lo lee Apache. Con Nginx hay que añadir el equivalente en la
configuración del sitio:

```nginx
location ^~ /uploads/ {
    location ~ \.php$ { return 403; }
    autoindex off;
}
```

---

## Datos que maneja el sistema

El sistema almacena datos de salud identificables (nombre, edad, sexo, historia clínica,
domicilio, cédula de identidad, diagnósticos de ingreso y egreso, y motivo clínico del alta).
Los diagnósticos son datos clínicos sensibles, así que estas recomendaciones no son
opcionales en un despliegue real. Recomendaciones mínimas para producción:

- Servir el sitio **solo por HTTPS**.
- Restringir el acceso a la página al personal del establecimiento (por ejemplo, con
  autenticación del hosting o restricción por red), ya que en este alcance la página no tiene
  control de usuarios.
- Hacer respaldo periódico de la base de datos y de la carpeta `uploads/altas/`.
