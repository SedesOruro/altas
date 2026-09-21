-- =====================================================================
-- Alta de dos usuarios del panel  —  Sistema "Alta Solicitada" (SEDES Oruro)
--
-- Ejecutar sobre la base ya creada:
--   mysql -u USUARIO -p sedes_altas < usuarios_ejemplo.sql
-- o pegarlo en la pestana SQL de phpMyAdmin.
--
-- CREDENCIALES QUE QUEDAN CREADAS
--   jperez    / Sedes2026*Oruro
--   mcondori  / Altas2026*Oruro
--
-- Las contrasenas van como hash bcrypt, que es lo que espera
-- password_verify(): NO se puede escribir la contrasena en texto plano
-- en la columna password_hash, porque entonces el login siempre fallaria.
--
-- Cambie estas contrasenas en cuanto entre, o use este archivo solo para
-- pruebas. Para generar el hash de otra contrasena:
--   php -r "echo password_hash('SU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO usuarios (username, nombre_completo, ci, telefono, correo, password_hash, activo)
VALUES
  ('jperez',
   'Juan Pérez Mamani',
   '4821567 OR',
   '59172345678',
   'jperez@sedesoruro.gob.bo',
   '$2y$10$1h8XQNxvSN1T/MnKlUwnYekcm/dNYBXVmM/essfJ9VBooiyL5HjkG',
   1),

  ('mcondori',
   'María Condori Choque',
   '5913402 OR',
   '59176543210',
   'mcondori@sedesoruro.gob.bo',
   '$2y$10$WEpYL/Ncthc55mTQTeqbGeobuEVIxstAo6URdnf0QuF3afQP1dayi',
   1);

-- Comprobacion
SELECT id, username, nombre_completo, ci, telefono, correo, activo
FROM usuarios
WHERE username IN ('jperez', 'mcondori');
