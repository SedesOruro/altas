-- =====================================================================
-- Migracion v1 -> v2   (Sistema "Alta Solicitada" - SEDES Oruro)
--
-- Adapta una base de datos ya instalada con el formato anterior al nuevo
-- "FORMULARIO DE NOTIFICACION DE ALTA SOLICITADA" (membrete institucional,
-- Red de salud / Municipio, Edad y Sexo, Hora de internacion, Diagnosticos
-- de ingreso y egreso, Grado de parentesco y Cedula/Pasaporte).
--
-- Ejecutar UNA sola vez:
--   mysql -u USUARIO -p NOMBRE_BD < migracion_v1_a_v2.sql
--
-- Los registros existentes se conservan. Como los campos nuevos no existian
-- antes, las filas anteriores quedan con valores provisionales visibles
-- ("(no registrado)", edad 0) que deben corregirse a mano si se necesitan.
-- Haga un respaldo antes de ejecutar:
--   mysqldump -u USUARIO -p NOMBRE_BD > respaldo_antes_v2.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Datos del Establecimiento de Salud ------------------------------
ALTER TABLE altas
  ADD COLUMN red_salud VARCHAR(255) NOT NULL DEFAULT '(no registrado)' AFTER nombre_establecimiento,
  ADD COLUMN municipio VARCHAR(255) NOT NULL DEFAULT '(no registrado)' AFTER red_salud;

-- 2. Informacion del Paciente ----------------------------------------
ALTER TABLE altas
  ADD COLUMN edad SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER nombre_paciente,
  ADD COLUMN edad_unidad ENUM('anios','meses','dias') NOT NULL DEFAULT 'anios' AFTER edad,
  ADD COLUMN sexo ENUM('M','F') NOT NULL DEFAULT 'M' AFTER edad_unidad;

-- 3. Detalles de la Internacion --------------------------------------
ALTER TABLE altas
  ADD COLUMN hora_internacion TIME NOT NULL DEFAULT '00:00:00' AFTER fecha_internacion,
  ADD COLUMN diagnosticos_ingreso VARCHAR(300) NOT NULL DEFAULT '(no registrado)' AFTER hora_internacion,
  ADD COLUMN diagnosticos_egreso VARCHAR(300) NOT NULL DEFAULT '(no registrado)' AFTER hora_solicitud;

-- 5. Firmas y Fecha ---------------------------------------------------
ALTER TABLE altas
  ADD COLUMN grado_parentesco VARCHAR(120) NULL AFTER motivo_alta;

-- "DNI/Documento de Identidad" pasa a llamarse "N de Cedula de Identidad/Pasaporte"
ALTER TABLE altas
  CHANGE COLUMN dni_documento ci_pasaporte VARCHAR(50) NOT NULL;

-- Indices utiles para listados y reportes ------------------------------
ALTER TABLE altas
  ADD INDEX idx_estado (estado),
  ADD INDEX idx_fecha_solicitud (fecha_solicitud);

-- Los valores por defecto solo sirvieron para poblar las filas antiguas:
-- se retiran para que en adelante toda alta llegue con datos reales.
ALTER TABLE altas
  ALTER COLUMN red_salud DROP DEFAULT,
  ALTER COLUMN municipio DROP DEFAULT,
  ALTER COLUMN edad DROP DEFAULT,
  ALTER COLUMN sexo DROP DEFAULT,
  ALTER COLUMN hora_internacion DROP DEFAULT,
  ALTER COLUMN diagnosticos_ingreso DROP DEFAULT,
  ALTER COLUMN diagnosticos_egreso DROP DEFAULT;
