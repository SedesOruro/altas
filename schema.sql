-- =====================================================================
-- Sistema "Alta Solicitada" - SEDES Oruro
-- Esquema de base de datos MySQL
-- Importar con: mysql -u USUARIO -p NOMBRE_BD < schema.sql
-- o desde phpMyAdmin -> Importar.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS altas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_alta VARCHAR(20) UNIQUE,
  nombre_establecimiento VARCHAR(255) NOT NULL,
  servicio_unidad VARCHAR(255) NOT NULL,
  nombre_paciente VARCHAR(255) NOT NULL,
  numero_historia_clinica VARCHAR(100) NOT NULL,
  numero_referencia VARCHAR(100) NULL,
  domicilio VARCHAR(255) NOT NULL,
  fecha_internacion DATE NOT NULL,
  motivo_alta TEXT NOT NULL,
  fecha_solicitud DATE NOT NULL,
  hora_solicitud TIME NOT NULL,
  dni_documento VARCHAR(50) NOT NULL,
  estado ENUM('generado','verificado','rechazado') DEFAULT 'generado',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
