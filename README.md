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
```

Las filas anteriores quedan con valores provisionales visibles (`(no registrado)`, edad `0`)
en los campos que antes no existían; corríjalos a mano si esos registros aún se usan.

Si ya tenía la versión 2 instalada, solo hace falta `migracion_v2_a_v3.sql`.

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
registro_altas.html        Formulario de altas (llenado + subida del firmado)
login.php                  Inicio de sesión del panel
registro.php               Alta de usuarios del panel
salir.php                  Cierre de sesión

admin/index.php            Panel: tabla de altas con filtros y paginación
admin/usuarios.php         Panel: listado y activación de usuarios
admin/listar_altas.php     GET   → JSON con las altas filtradas y paginadas
admin/ver_adjunto.php      GET   → entrega el documento firmado (exige sesión)
admin/_plantilla.php       Armazón AdminLTE común a las páginas del panel

assets/style.css           Estilos
assets/app.js              Validación en cliente y llamadas fetch()
assets/calendario.js       Selector de fecha táctil que sustituye al campo nativo
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
lib/helpers.php            Respuestas JSON, validación, limitador de intentos
lib/pdf_alta.php           Maquetación del PDF (fidelidad al formato Word)
lib/fpdf/                  Librería FPDF 1.86 (incluida, sin Composer)

schema.sql                 Creación de las tablas MySQL (instalación nueva)
migracion_v1_a_v2.sql      Actualización de la v1 al formulario nuevo
migracion_v2_a_v3.sql      Agrega la tabla de usuarios del panel

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

Los campos de fecha **no usan el selector nativo del navegador**, cuyo comportamiento en móvil
cambia mucho de un equipo a otro. `assets/calendario.js` los convierte en un botón que muestra
la fecha en formato `dd/mm/aaaa` y abre un calendario propio con selectores de mes y año,
cuadrícula de días táctil y atajos «Hoy» y «Ayer». El `<input>` original se conserva como
`hidden`, así que el envío y la validación no cambian.

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

**Usuarios** lista las cuentas y permite activarlas o desactivarlas. Las cuentas no se
borran: una cuenta eliminada dejaría sin explicación los accesos ya registrados, y
desactivarla cumple la misma función.

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
| 5. Firmas | `grado_parentesco` *(opcional)*, `ci_pasaporte` |

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
