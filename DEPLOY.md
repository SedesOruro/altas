# Despliegue en CapRover — https://altas.app.sedesoruro.gob.bo

Guía completa para publicar el sistema de Alta Solicitada en CapRover.

El proyecto se empaqueta en una imagen **Apache + mod_php**: un solo contenedor, sin
procesos adicionales. Los documentos firmados que sube el personal **se guardan en el disco
del contenedor**, así que el punto más importante de esta guía es el **paso 4 (directorios
persistentes)**: sin él, cada nuevo despliegue borraría todos los archivos subidos.

---

## Índice

1. [Requisitos previos](#1-requisitos-previos)
2. [Base de datos MySQL](#2-base-de-datos-mysql)
3. [Crear la aplicación en CapRover](#3-crear-la-aplicación-en-caprover)
4. [Directorios persistentes (documentos subidos)](#4-directorios-persistentes-documentos-subidos)
5. [Variables de entorno](#5-variables-de-entorno)
6. [Dominio y HTTPS](#6-dominio-y-https)
7. [Desplegar el código](#7-desplegar-el-código)
8. [Importar el esquema de la base de datos](#8-importar-el-esquema-de-la-base-de-datos)
9. [Verificación](#9-verificación)
10. [Actualizaciones posteriores](#10-actualizaciones-posteriores)
11. [Respaldos](#11-respaldos)
12. [Solución de problemas](#12-solución-de-problemas)

---

## 1. Requisitos previos

- Un servidor con **CapRover** instalado y funcionando.
- El **dominio raíz de CapRover** configurado como `app.sedesoruro.gob.bo`.
  Con ese dominio raíz, una aplicación llamada `altas` queda publicada automáticamente en
  `https://altas.app.sedesoruro.gob.bo`, que es la dirección solicitada.
- En el DNS de `sedesoruro.gob.bo`, un registro comodín apuntando a la IP del servidor:

  ```
  *.app.sedesoruro.gob.bo.   A   <IP del servidor CapRover>
  ```

  Si no se puede usar comodín, basta un registro `A` para `altas.app.sedesoruro.gob.bo`.
- Acceso al panel de CapRover (`https://captain.app.sedesoruro.gob.bo`).

> **Si el dominio raíz de CapRover es otro** (por ejemplo `caprover.sedesoruro.gob.bo`), la
> app igual funciona: se le agrega `altas.app.sedesoruro.gob.bo` como dominio personalizado
> en el paso 6.

### Archivos de despliegue que ya incluye el proyecto

| Archivo | Para qué sirve |
|---|---|
| `captain-definition` | Le indica a CapRover que construya con el `Dockerfile` |
| `Dockerfile` | Imagen `php:8.3-apache` con `pdo_mysql` y la app en `/var/www/html` |
| `docker/apache-altas.conf` | Configuración y reglas de seguridad de Apache (incluye el cierre de `uploads/`) |
| `docker/php-altas.ini` | Límites de subida, zona horaria, errores al log |
| `docker/entrypoint.sh` | Da permisos de escritura a los volúmenes antes de arrancar Apache |
| `.dockerignore` | Evita que `config.local.php`, `data/` y los adjuntos locales entren en la imagen |

---

## 2. Base de datos MySQL

La aplicación necesita MySQL 5.7+ o MariaDB. Hay dos caminos.

### Opción A — MySQL dentro de CapRover (recomendada si no hay un servidor propio)

1. **Apps → One-Click Apps/Databases → MySQL**.
2. Complete el formulario:
   - *App Name*: `mysql`
   - *MySQL Root Password*: una contraseña fuerte (guárdela)
   - *MySQL Database*: `sedes_altas`
   - *MySQL User* / *Password*: `altas_app` y otra contraseña fuerte
3. Despliegue y espere a que el contenedor quede en verde.

El nombre de host interno será **`srv-captain--mysql`**. Ese es el valor de `DB_HOST`.
No exponga el puerto 3306 hacia internet: la app y la base se comunican por la red interna
de CapRover.

### Opción B — Servidor MySQL institucional ya existente

Cree la base y el usuario:

```sql
CREATE DATABASE sedes_altas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'altas_app'@'%' IDENTIFIED BY 'CLAVE_FUERTE';
GRANT SELECT, INSERT, UPDATE, DELETE ON sedes_altas.* TO 'altas_app'@'%';
FLUSH PRIVILEGES;
```

`DB_HOST` será la IP o el nombre DNS de ese servidor, que debe ser alcanzable desde el
servidor de CapRover.

> El usuario de la aplicación **no necesita** permisos `CREATE`, `DROP` ni `ALTER`. El
> esquema se importa una sola vez con el usuario administrador (paso 8).

---

## 3. Crear la aplicación en CapRover

1. **Apps → Create A New App**.
2. *App Name*: **`altas`** (el nombre determina la URL).
3. **No** marque «Has Persistent Data» en esta pantalla; los directorios se configuran en el
   paso siguiente, que es donde CapRover permite indicar las rutas.
4. **Create New App**.

Luego entre a la app y en **App Configs**:

- *Container HTTP Port*: **80** (es el puerto que expone Apache).
- *Instance Count*: **1**.
  Con directorios persistentes en disco local, **no escale a más de una instancia**: cada
  réplica vería una copia distinta de los archivos. Si en el futuro hace falta escalar,
  habrá que mover los adjuntos a un volumen compartido (NFS) o a almacenamiento tipo S3.

---

## 4. Directorios persistentes (documentos subidos)

**Este es el paso crítico.** El sistema guarda los formularios firmados en el sistema de
archivos, no en la base de datos:

- `uploads/altas/` → los PDF y JPG firmados que sube el personal.
- `data/` → contadores del limitador de intentos (evita fuerza bruta sobre los códigos).

Un contenedor Docker es efímero: **todo lo que se escriba fuera de un volumen se pierde en el
siguiente despliegue o reinicio**. Por eso hay que declarar ambas rutas como persistentes.

En **App Configs** de la app `altas`:

1. Active **«Has Persistent Data»**.
2. Agregue dos directorios (*Add Persistent Directory*):

   | Path in App (ruta dentro del contenedor) | Label (nombre del volumen) |
   |---|---|
   | `/var/www/html/uploads/altas` | `altas-uploads` |
   | `/var/www/html/data` | `altas-data` |

3. **Save & Update**.

CapRover creará los volúmenes en el servidor, normalmente bajo
`/var/lib/docker/volumes/captain--altas-uploads/_data`.

### Dos detalles importantes

**Permisos.** CapRover monta los volúmenes como `root` y Apache corre como `www-data`. El
`docker/entrypoint.sh` incluido resuelve esto en cada arranque: crea las carpetas si faltan y
les ajusta el propietario y los permisos. No hay que hacer nada manualmente.

**Seguridad de la carpeta de subidas.** El repositorio trae un `uploads/altas/.htaccess` con
las reglas de protección, pero **al montar el volumen ese archivo queda oculto bajo el punto
de montaje**. Por eso las mismas reglas están duplicadas en `docker/apache-altas.conf`, que
vive en la imagen y siempre se aplica: la carpeta está denegada por HTTP, el motor PHP
apagado, los handlers de script removidos y el listado de directorio desactivado. Un `.php`
disfrazado de PDF no puede ejecutarse, y los escaneos firmados solo se ven desde el panel,
con sesión iniciada.

---

## 5. Variables de entorno

La aplicación lee su configuración del entorno; **no** se sube ningún `config.local.php` al
servidor (el `.dockerignore` lo excluye y el `Dockerfile` lo borra por si acaso).

En **App Configs → Environmental Variables**, agregue:

| Variable | Valor | Notas |
|---|---|---|
| `DB_HOST` | `srv-captain--mysql` | O la IP/host del MySQL institucional |
| `DB_PORT` | `3306` | |
| `DB_NAME` | `sedes_altas` | |
| `DB_USER` | `altas_app` | |
| `DB_PASS` | *(la contraseña)* | |
| `APP_DEBUG` | `false` | **Nunca** `true` en producción |
| `APP_TIMEZONE` | `America/La_Paz` | Opcional; es el valor por defecto |

Pulse **Save & Update**.

> `APP_DEBUG` acepta `false`, `0`, `off` o `no` para desactivar el modo depuración. Con él
> apagado, los errores de PHP y MySQL no se muestran al usuario: van al log del contenedor,
> visible en **App Logs**.

---

## 6. Dominio y HTTPS

En **App Configs → HTTP Settings** de la app `altas`:

1. Si el dominio raíz de CapRover es `app.sedesoruro.gob.bo`, la URL
   `https://altas.app.sedesoruro.gob.bo` ya aparece asignada. Pulse **Enable HTTPS** y
   confirme; CapRover emite el certificado con Let's Encrypt.
2. Si el dominio raíz es otro: en *Connect New Domain* escriba
   `altas.app.sedesoruro.gob.bo`, guarde y luego **Enable HTTPS** para ese dominio.
3. Active **«Force HTTPS by redirecting all HTTP traffic to HTTPS»**.

Este sistema maneja datos clínicos identificables: **HTTPS forzado no es opcional.**

Para que Let's Encrypt emita el certificado, el dominio debe resolver a la IP del servidor
**antes** de pulsar *Enable HTTPS*. Compruébelo con:

```bash
nslookup altas.app.sedesoruro.gob.bo
```

---

## 7. Desplegar el código

El repositorio es `https://github.com/SedesOruro/altas.git`. Elija un método.

### Método A — Desde GitHub (recomendado)

En **Deployment** de la app:

1. Sección *Method 3: Deploy from Github/Bitbucket/Gitlab*.
2. Complete:
   - *Repository*: `github.com/SedesOruro/altas`
   - *Branch*: `master`
   - *Username*: su usuario de GitHub
   - *Password / Token*: un **Personal Access Token** con permiso `repo`
     (si el repositorio es privado; si es público puede quedar vacío)
3. **Save & Update**. CapRover muestra una **webhook URL**.
4. Copie esa URL y péguela en GitHub: *Settings → Webhooks → Add webhook*,
   *Content type*: `application/json`.

Desde ese momento, cada `git push` a `master` construye y despliega automáticamente.
Para lanzar el primer despliegue sin esperar un push, pulse **Force Build**.

### Método B — Desde la máquina de desarrollo con la CLI

```bash
npm install -g caprover
caprover login
```

Y en la carpeta del proyecto:

```bash
caprover deploy
```

Elija el servidor y la app `altas`. La CLI envía el contenido del repositorio (respetando
`.gitignore`), CapRover construye la imagen y la publica.

### Método C — Subiendo un archivo comprimido

1. Genere un `.tar` con el contenido del proyecto (el `captain-definition` debe quedar en la
   **raíz** del archivo, no dentro de una subcarpeta):

   ```bash
   git archive --format=tar --output=../altas.tar HEAD
   ```

2. En **Deployment → Method 2: Tarball**, suba el archivo.

En los tres métodos la construcción tarda 1–3 minutos la primera vez. El progreso se ve en
la misma pantalla.

---

## 8. Importar el esquema de la base de datos

La aplicación no crea tablas por sí sola. Hágalo **una sola vez**, después del primer
despliegue exitoso.

### Con phpMyAdmin (lo más simple)

1. Instale el One-Click App **phpMyAdmin** en CapRover.
2. En sus variables de entorno, `PMA_HOST` = `srv-captain--mysql`.
3. Entre con el usuario `root` de MySQL, seleccione la base `sedes_altas` y use
   **Importar** con el archivo `schema.sql` del repositorio.
4. Cuando termine, **elimine la app de phpMyAdmin** o restrínjale el acceso: no debe quedar
   expuesta permanentemente.

### Por consola, en el servidor de CapRover

```bash
docker exec -i $(docker ps -qf name=srv-captain--mysql) \
  mysql -u root -p'CLAVE_ROOT' sedes_altas < schema.sql
```

(El archivo `schema.sql` debe estar en el servidor; cópielo con `scp` si hace falta.)

### Si ya existía una instalación con el formato anterior

No importe `schema.sql`: usaría tablas nuevas y perdería los registros. Ejecute las
migraciones, que conservan los datos:

```bash
mysqldump -u root -p sedes_altas > respaldo_antes_de_migrar.sql
mysql -u root -p sedes_altas < migracion_v1_a_v2.sql   # campos del formulario nuevo
mysql -u root -p sedes_altas < migracion_v2_a_v3.sql   # tabla de usuarios del panel
mysql -u root -p sedes_altas < migracion_v3_a_v4.sql   # datos de quien firma el alta
```

### Crear el primer usuario del panel

Con la base ya lista, abra `https://altas.app.sedesoruro.gob.bo/registro.php` y cree la
cuenta del administrador. **Hágalo en cuanto el sitio esté publicado**: esa página solo está
abierta mientras no exista ningún usuario, y creado el primero exige haber iniciado sesión.
Dejarla sin usar es dejar la puerta entornada.

---

## 9. Verificación

Recorra esta lista después del primer despliegue:

1. **La página carga.** Abra `https://altas.app.sedesoruro.gob.bo` — debe verse el membrete
   con el escudo de Oruro y el candado de HTTPS.
2. **Hay conexión con la base.** Registre una solicitud de prueba: si aparece un código
   `ALTA-000001`, PHP está hablando con MySQL correctamente.
3. **El PDF se genera.** Pulse *Descargar PDF* y confirme que sale en una página, con el
   membrete y el código.
4. **La subida funciona.** En *Subir alta firmada*, ingrese ese código y suba el mismo PDF.
   Debe responder que el documento fue recibido.
5. **Los archivos persisten** (la prueba más importante): en **Deployment**, pulse
   **Force Build** para redesplegar y, cuando termine, verifique en el servidor que el
   archivo sigue ahí:

   ```bash
   ls -l /var/lib/docker/volumes/captain--altas-uploads/_data
   ```

   Si el archivo desapareció, los directorios persistentes no quedaron bien configurados:
   vuelva al paso 4.
6. **El panel funciona.** Entre con el usuario creado, compruebe que la fila de prueba
   aparece en la tabla y que sus dos botones de PDF abren los documentos correctos.
7. **Las carpetas internas están cerradas.** Estas URLs deben devolver **403 Forbidden**:

   ```
   https://altas.app.sedesoruro.gob.bo/data/
   https://altas.app.sedesoruro.gob.bo/uploads/altas/
   https://altas.app.sedesoruro.gob.bo/schema.sql
   https://altas.app.sedesoruro.gob.bo/config.local.php
   https://altas.app.sedesoruro.gob.bo/lib/auth.php
   ```

   Los documentos firmados solo deben abrirse desde el panel, con sesión iniciada.

8. **Borre los datos de prueba** cuando termine:

   ```sql
   DELETE FROM alta_adjuntos; DELETE FROM altas; ALTER TABLE altas AUTO_INCREMENT = 1;
   ```

   Y elimine también el archivo de prueba del volumen de subidas.

---

## 10. Actualizaciones posteriores

Con el método A basta con `git push` a `master`; la webhook dispara la construcción.

Con la CLI:

```bash
caprover deploy
```

Los volúmenes **no se tocan** al redesplegar: los documentos subidos y los registros de la
base sobreviven. Si un despliegue sale mal, en **Deployment → Version History** puede volver
a una versión anterior con un clic.

---

## 11. Respaldos

Hay que respaldar **dos cosas**; una sola no sirve, porque cada registro de la base apunta a
un archivo en disco.

**Base de datos:**

```bash
docker exec $(docker ps -qf name=srv-captain--mysql) \
  mysqldump -u root -p'CLAVE_ROOT' sedes_altas | gzip > altas_$(date +%F).sql.gz
```

**Documentos firmados:**

```bash
tar czf altas_uploads_$(date +%F).tar.gz \
  -C /var/lib/docker/volumes/captain--altas-uploads/_data .
```

Programe ambos como tarea diaria (`cron`) y guarde las copias fuera del servidor.

---

## 12. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| «No fue posible conectar con la base de datos» | Variables `DB_*` mal puestas, o MySQL aún iniciando | Revise **App Logs**; confirme que `DB_HOST` es `srv-captain--mysql` y que la base ya está en verde |
| «Base table 'altas' doesn't exist» | Falta importar el esquema | Paso 8 |
| «No fue posible almacenar el archivo en el servidor» | El volumen no tiene permisos de escritura | Reinicie la app: el `entrypoint.sh` corrige el propietario al arrancar. Si persiste, ejecute en el servidor `chown -R 33:33 /var/lib/docker/volumes/captain--altas-uploads/_data` (33 es `www-data`) |
| Los archivos subidos desaparecen tras un despliegue | Los directorios persistentes no están configurados | Paso 4 |
| Error **413** al subir un archivo grande | Límite del proxy Nginx de CapRover | En **App Configs → Custom Nginx Configuration**, agregue `client_max_body_size 20m;` dentro del bloque `location /` |
| Error de subida con archivos de 10–12 MB | Límites de PHP | Ya están en `docker/php-altas.ini` (`upload_max_filesize 12M`); si necesita más, súbalos ahí y redespliegue |
| La construcción falla en `docker-php-ext-install` | Sin salida a internet en el servidor, o Docker Hub caído | Reintente; revise el log de construcción |
| El certificado HTTPS no se emite | El DNS aún no apunta al servidor | Espere la propagación del DNS y vuelva a pulsar *Enable HTTPS* |
| La página muestra errores de PHP en pantalla | `APP_DEBUG` quedó activo | Póngalo en `false` y **Save & Update** |
| Se ven listados de carpetas o se descarga el código | La configuración de Apache no se aplicó | Confirme que la construcción usó el `Dockerfile` del repositorio (`captain-definition` en la raíz) |

Para ver qué está pasando dentro del contenedor:

```bash
# Logs de la aplicación (también visibles en CapRover > App Logs)
docker service logs srv-captain--altas --tail 100

# Entrar al contenedor
docker exec -it $(docker ps -qf name=srv-captain--altas) bash
```

---

## Nota final sobre protección de datos

El sistema almacena datos clínicos identificables (nombre, edad, sexo, historia clínica,
domicilio, cédula de identidad, diagnósticos y motivo del alta).

El **panel de administración** exige usuario y contraseña, y los documentos firmados solo se
entregan con sesión iniciada: la carpeta `uploads/` está denegada por HTTP y los archivos
salen únicamente desde `admin/ver_adjunto.php`.

El **formulario público**, en cambio, no tiene control de usuarios: cualquiera que alcance la
URL puede registrar altas y consultar si un código existe. Para esa parte, considere al menos
una de estas medidas antes de ponerlo en producción:

- Restringir el acceso por IP o por red institucional desde
  **App Configs → Custom Nginx Configuration** (`allow` / `deny`).
- Agregar autenticación básica HTTP en la misma configuración de Nginx.
- Publicarlo solo en la intranet del SEDES, sin exposición a internet.

Junto con el HTTPS forzado del paso 6 y los respaldos del paso 11, eso cubre lo mínimo
razonable para información de salud.
