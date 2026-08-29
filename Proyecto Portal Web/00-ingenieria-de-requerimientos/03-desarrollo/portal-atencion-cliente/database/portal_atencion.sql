-- =========================================================
-- Base de datos: portal_atencion
-- Portal web de atención al cliente 24/7
-- Importar este archivo desde phpMyAdmin (XAMPP) -> pestaña "Importar"
-- =========================================================

CREATE DATABASE IF NOT EXISTS portal_atencion
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE portal_atencion;

-- ---------------------------------------------------------
-- Tabla: clientes
-- ---------------------------------------------------------
CREATE TABLE clientes (
  id_cliente      INT AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(150) NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  telefono        VARCHAR(30)  NULL,
  fecha_registro  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: categorias (tipos de solicitud)
-- ---------------------------------------------------------
CREATE TABLE categorias (
  id_categoria      INT AUTO_INCREMENT PRIMARY KEY,
  nombre_categoria  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categorias (nombre_categoria) VALUES
  ('Soporte técnico'),
  ('Facturación'),
  ('Información de producto'),
  ('Reclamo'),
  ('Otro');

-- ---------------------------------------------------------
-- Tabla: solicitudes
-- ---------------------------------------------------------
CREATE TABLE solicitudes (
  id_solicitud          INT AUTO_INCREMENT PRIMARY KEY,
  codigo_caso           VARCHAR(20) NOT NULL UNIQUE,
  id_cliente            INT NOT NULL,
  id_categoria          INT NULL,
  prioridad             ENUM('Baja','Media','Alta','Urgente') NOT NULL DEFAULT 'Media',
  descripcion           TEXT NOT NULL,
  estado                ENUM('Abierto','En proceso','Resuelto','Cerrado') NOT NULL DEFAULT 'Abierto',
  canal_origen          VARCHAR(50) DEFAULT 'Portal web',
  fecha_creacion        DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_solicitud_cliente
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
  CONSTRAINT fk_solicitud_categoria
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: historial_solicitud (trazabilidad de cambios de estado)
-- ---------------------------------------------------------
CREATE TABLE historial_solicitud (
  id_historial     INT AUTO_INCREMENT PRIMARY KEY,
  id_solicitud     INT NOT NULL,
  estado_anterior  VARCHAR(30) NULL,
  estado_nuevo     VARCHAR(30) NOT NULL,
  fecha_cambio     DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_historial_solicitud
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabla: ia_analisis (análisis de IA por solicitud)
-- ---------------------------------------------------------
CREATE TABLE ia_analisis (
  id_analisis     INT AUTO_INCREMENT PRIMARY KEY,
  id_solicitud    INT NOT NULL UNIQUE,
  analisis_json   JSON NOT NULL,
  creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ia_solicitud
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Vista: reporte mensual (para el panel administrativo)
-- ---------------------------------------------------------
CREATE OR REPLACE VIEW vista_reporte_mensual AS
SELECT
  DATE_FORMAT(fecha_creacion, '%Y-%m')                     AS mes,
  COUNT(*)                                                 AS total_solicitudes,
  SUM(estado IN ('Resuelto','Cerrado'))                    AS resueltas,
  SUM(estado NOT IN ('Resuelto','Cerrado'))                AS pendientes
FROM solicitudes
GROUP BY mes
ORDER BY mes;

-- ---------------------------------------------------------
-- Datos de ejemplo (opcional, para probar el portal de inmediato)
-- ---------------------------------------------------------
INSERT INTO clientes (nombre, email, telefono) VALUES
  ('Laura Gómez', 'laura@correo.com', '3001234567'),
  ('Carlos Pérez', 'carlos@correo.com', '3009876543');

INSERT INTO solicitudes (codigo_caso, id_cliente, id_categoria, prioridad, descripcion, estado, fecha_creacion) VALUES
  ('CS-2026-0001', 1, 1, 'Alta',  'No puedo acceder a mi cuenta desde ayer.', 'Resuelto', '2026-06-14 09:12:00'),
  ('CS-2026-0002', 2, 2, 'Media', 'Solicito copia de la factura del mes de julio.', 'En proceso', '2026-07-22 15:40:00'),
  ('CS-2026-0003', 1, 3, 'Baja',  'Quiero conocer los planes disponibles.', 'Abierto', '2026-08-10 11:05:00');