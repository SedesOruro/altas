-- =====================================================================
-- Migracion v3 -> v4
--
-- Amplia la seccion «5. Firmas y Fecha» con los datos de la persona que
-- firma el alta. Antes solo se guardaban el grado de parentesco y la
-- cedula; ahora se identifica al firmante por nombre y se registran sus
-- datos de contacto, que es lo que permite ubicarlo despues si hay que
-- aclarar algo sobre un alta ya firmada.
--
-- El orden de las columnas sigue el del formulario impreso:
--   grado de parentesco -> nombre completo -> cedula -> telefono -> direccion
--
-- Las altas ya registradas se quedan con el nombre vacio y el telefono y
-- la direccion en NULL: son datos que nadie pidio cuando se llenaron, y
-- el panel los muestra como «—».
--
-- Aplicar una sola vez:
--   mysql -u root -p sedes_altas < migracion_v3_a_v4.sql
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE altas
  ADD COLUMN nombre_firmante VARCHAR(255) NOT NULL DEFAULT '' AFTER grado_parentesco,
  ADD COLUMN telefono_firmante VARCHAR(50) NULL AFTER ci_pasaporte,
  ADD COLUMN direccion_firmante VARCHAR(255) NULL AFTER telefono_firmante;

-- El DEFAULT existe solo para poder rellenar las filas antiguas; de aqui
-- en adelante el nombre es obligatorio y lo envia siempre el formulario.
ALTER TABLE altas ALTER COLUMN nombre_firmante DROP DEFAULT;
