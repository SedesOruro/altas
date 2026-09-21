-- =====================================================================
-- Sistema "Alta Solicitada" - SEDES Oruro
-- Esquema de base de datos MySQL  (v3 - Formulario con membrete + panel)
-- Importar con: mysql -u USUARIO -p NOMBRE_BD < schema.sql
-- o desde phpMyAdmin -> Importar.
--
-- Si ya tiene una version anterior instalada, NO ejecute este archivo:
-- use migracion_v1_a_v2.sql y/o migracion_v2_a_v3.sql, que conservan
-- los registros existentes.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS altas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_alta VARCHAR(20) UNIQUE,

  -- 1. Datos del Establecimiento de Salud
  nombre_establecimiento VARCHAR(255) NOT NULL,
  red_salud VARCHAR(255) NOT NULL,
  municipio VARCHAR(255) NOT NULL,
  servicio_unidad VARCHAR(255) NOT NULL,

  -- 2. Informacion del Paciente
  nombre_paciente VARCHAR(255) NOT NULL,
  edad SMALLINT UNSIGNED NOT NULL,
  edad_unidad ENUM('anios','meses','dias') NOT NULL DEFAULT 'anios',
  sexo ENUM('M','F') NOT NULL,
  numero_historia_clinica VARCHAR(100) NOT NULL,
  numero_referencia VARCHAR(100) NULL,
  domicilio VARCHAR(255) NOT NULL,

  -- 3. Detalles de la Internacion
  fecha_internacion DATE NOT NULL,
  hora_internacion TIME NOT NULL,
  diagnosticos_ingreso VARCHAR(300) NOT NULL,
  fecha_solicitud DATE NOT NULL,
  hora_solicitud TIME NOT NULL,
  diagnosticos_egreso VARCHAR(300) NOT NULL,

  -- 4. Declaracion de Alta Solicitada
  motivo_alta TEXT NOT NULL,

  -- 5. Firmas y Fecha
  grado_parentesco VARCHAR(120) NULL,
  ci_pasaporte VARCHAR(50) NOT NULL,

  estado ENUM('generado','verificado','rechazado') DEFAULT 'generado',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_estado (estado),
  INDEX idx_fecha_solicitud (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alta_adjuntos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alta_id INT NOT NULL,
  codigo_alta VARCHAR(20) NOT NULL,
  nombre_archivo_original VARCHAR(255),
  nombre_archivo_guardado VARCHAR(255),
  ruta_archivo VARCHAR(500),
  tipo_mime VARCHAR(100),
  tamano_bytes INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_codigo_alta (codigo_alta),
  FOREIGN KEY (alta_id) REFERENCES altas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Usuarios del panel de administracion.
-- La contrasena se guarda como hash de password_hash() (bcrypt): en la
-- base de datos nunca hay contrasenas en texto plano.
-- =====================================================================

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
