-- =====================================================================
-- Migracion v2 -> v3   (Sistema "Alta Solicitada" - SEDES Oruro)
--
-- Agrega la tabla de usuarios del panel de administracion. No toca las
-- tablas `altas` ni `alta_adjuntos`, asi que no hay riesgo para los
-- registros existentes.
--
-- Ejecutar una sola vez:
--   mysql -u USUARIO -p NOMBRE_BD < migracion_v2_a_v3.sql
--
-- Despues, cree el primer usuario administrador desde la pagina
-- registro.php del sitio (queda abierta solo mientras no exista ningun
-- usuario; a partir del primero exige haber iniciado sesion).
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  nombre_completo VARCHAR(160) NOT NULL,
  ci VARCHAR(30) NOT NULL UNIQUE,
  telefono VARCHAR(30) NOT NULL,
  correo VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
