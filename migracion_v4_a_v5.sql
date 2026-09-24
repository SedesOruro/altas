-- =====================================================================
-- Migracion v4 -> v5
--
-- Introduce los dos roles del sistema:
--
--   administrador  ve y administra todo: altas, usuarios y las acciones
--                  del panel (devolver un alta a pendiente).
--   operador       entra al panel para registrar altas nuevas y consultar
--                  el listado; no administra usuarios.
--
-- Las cuentas que ya existian se quedan como administrador: eran las
-- unicas que habia y tenian el panel completo, asi que rebajarlas de
-- golpe dejaria el sistema sin quien lo administre.
--
-- Aplicar una sola vez:
--   mysql -u root -p sedes_altas < migracion_v4_a_v5.sql
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE usuarios
  ADD COLUMN rol ENUM('administrador','operador') NOT NULL DEFAULT 'administrador' AFTER correo;

-- De aqui en adelante, una cuenta nueva nace con el rol mas limitado:
-- el formulario de registro elige el rol de forma explicita.
ALTER TABLE usuarios ALTER COLUMN rol SET DEFAULT 'operador';

CREATE INDEX idx_rol ON usuarios (rol);
