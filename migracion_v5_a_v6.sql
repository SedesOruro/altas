-- =====================================================================
-- Migracion v5 -> v6
--
-- Ata cada operador a su establecimiento de salud.
--
-- Hasta aqui cualquier persona autenticada podia registrar un alta a
-- nombre de cualquier hospital del departamento: el establecimiento salia
-- de un desplegable del formulario. Con estas dos columnas, el operador
-- lleva su red y su establecimiento en la cuenta, el formulario los
-- muestra fijos y el servidor los toma de la sesion, no del navegador.
--
-- Quedan en NULL para el administrador, que ve y registra en todos los
-- establecimientos, y tambien para los operadores que ya existan: hay que
-- asignarles el suyo desde Usuarios -> Editar antes de que vuelvan a
-- entrar. Mientras no lo tengan, siguen viendo el desplegable completo.
--
-- Aplicar una sola vez:
--   mysql -u root -p sedes_altas < migracion_v5_a_v6.sql
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE usuarios
  ADD COLUMN red_salud VARCHAR(255) NULL AFTER rol,
  ADD COLUMN nombre_establecimiento VARCHAR(255) NULL AFTER red_salud;

CREATE INDEX idx_establecimiento ON usuarios (nombre_establecimiento);
