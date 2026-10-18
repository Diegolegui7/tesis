-- =========================================================
-- ARPAFLU · Sistema de seguimiento de reservas
-- Esquema de base de datos (MySQL 5.7 / MariaDB 10.3)
-- Corresponde a los archivos definidos en el Diseño Global.
-- Para pruebas locales. Lo ejecuta instalar.php (o se puede
-- importar a mano desde phpMyAdmin).
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS demo;
DROP TABLE IF EXISTS solicitudes;
DROP TABLE IF EXISTS disponibilidad;
DROP TABLE IF EXISTS operacion_destinatarios;
DROP TABLE IF EXISTS comunicaciones;
DROP TABLE IF EXISTS historial_cambios;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS contenedores;
DROP TABLE IF EXISTS alternativas;
DROP TABLE IF EXISTS operaciones;
DROP TABLE IF EXISTS cotizaciones;
DROP TABLE IF EXISTS participantes;
DROP TABLE IF EXISTS asignaciones;
DROP TABLE IF EXISTS permisos_cliente;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS buques;
DROP TABLE IF EXISTS navieras;
DROP TABLE IF EXISTS puertos;
DROP TABLE IF EXISTS terminales;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Tablas de referencia (SS2 / SS3) ----------

CREATE TABLE navieras (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE buques (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  naviera_id INT UNSIGNED NULL,
  nombre VARCHAR(120) NOT NULL,
  bandera VARCHAR(60) NULL,
  FOREIGN KEY (naviera_id) REFERENCES navieras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE puertos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  es_origen TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE terminales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SS1 · Seguridad y administración de accesos ----------

CREATE TABLE clientes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  empresa VARCHAR(120) NOT NULL,
  cuit VARCHAR(20) NULL,
  contacto VARCHAR(120) NULL,
  correo VARCHAR(160) NULL,
  despachante VARCHAR(120) NULL,
  correo_despachante VARCHAR(160) NULL,
  canal ENUM('correo','whatsapp') NOT NULL DEFAULT 'correo',
  responsable_id INT UNSIGNED NULL,
  selecciona_alternativas TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(160) NOT NULL UNIQUE,
  clave_hash VARCHAR(255) NOT NULL,
  tipo ENUM('empleado','cliente') NOT NULL,
  rol ENUM('administrador','operaciones','comercial','consulta','cliente') NOT NULL,
  cliente_id INT UNSIGNED NULL,
  puede_seleccionar TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_ingreso DATETIME NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE clientes
  ADD CONSTRAINT fk_clientes_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id);

-- Permisos por función (RF-01)
CREATE TABLE permisos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  funcion VARCHAR(40) NOT NULL,
  permitido TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_permiso (usuario_id, funcion),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permisos por cliente (RF-02): sobre qué clientes puede actuar cada empleado
CREATE TABLE permisos_cliente (
  usuario_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  nivel ENUM('consulta','gestion') NOT NULL DEFAULT 'gestion',
  PRIMARY KEY (usuario_id, cliente_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Histórico de asignación de clientes a empleados (RF-03)
CREATE TABLE asignaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  desde DATE NOT NULL,
  hasta DATE NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contactos y despachantes que reciben avisos (SS5)
CREATE TABLE participantes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(160) NOT NULL,
  rol ENUM('contacto','despachante','otro') NOT NULL DEFAULT 'contacto',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SS2 · Gestión comercial y de reservas ----------

CREATE TABLE cotizaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(12) NOT NULL,
  version TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cliente_id INT UNSIGNED NOT NULL,
  naviera_id INT UNSIGNED NULL,
  pod VARCHAR(80) NOT NULL,
  contenedores VARCHAR(60) NULL,
  flete DECIMAL(10,2) NOT NULL DEFAULT 0,
  costo_interno DECIMAL(10,2) NULL,
  condiciones TEXT NULL,
  vigencia DATE NULL,
  estado ENUM('enviada','aceptada','rechazada','vencida','reemplazada') NOT NULL DEFAULT 'enviada',
  archivo_nombre VARCHAR(200) NULL,
  archivo_ruta VARCHAR(300) NULL,
  creado_por INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cotizacion (codigo, version),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (naviera_id) REFERENCES navieras(id),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Operaciones: archivo central (SS2 y SS3)
CREATE TABLE operaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(12) NOT NULL UNIQUE,
  cliente_id INT UNSIGNED NOT NULL,
  referencia_cliente VARCHAR(60) NULL,
  cotizacion_id INT UNSIGNED NULL,
  responsable_id INT UNSIGNED NULL,
  naviera_id INT UNSIGNED NULL,
  buque VARCHAR(120) NULL,
  booking VARCHAR(40) NULL,
  semana TINYINT UNSIGNED NULL,
  pol VARCHAR(80) NULL,
  pod VARCHAR(80) NOT NULL,
  via ENUM('directa','transbordo') NOT NULL DEFAULT 'directa',
  terminal_id INT UNSIGNED NULL,
  cantidad SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  tipo_contenedor VARCHAR(20) NULL,
  dias_libres SMALLINT UNSIGNED NULL,
  etd DATE NULL,
  eta DATE NULL,
  cutoff_doc DATETIME NULL,
  cutoff_fisico DATETIME NULL,
  vgm_limite DATETIME NULL,
  booking_confirmado_en DATETIME NULL,
  orden_retiro VARCHAR(40) NULL,
  orden_retiro_fecha DATE NULL,
  estado ENUM('reserva_solicitada','booking_confirmado','orden_retiro','documentacion','zarpado','cancelada') NOT NULL DEFAULT 'reserva_solicitada',
  zarpe_confirmado DATETIME NULL,
  cancelada_por ENUM('cliente','naviera','arpaflu') NULL,
  cancelacion_motivo TEXT NULL,
  cancelada_en DATETIME NULL,
  cancelacion_archivo VARCHAR(300) NULL,
  observaciones TEXT NULL,
  creado_por INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_op_cliente (cliente_id),
  KEY idx_op_estado (estado),
  KEY idx_op_cutoff (cutoff_doc),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id),
  FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
  FOREIGN KEY (naviera_id) REFERENCES navieras(id),
  FOREIGN KEY (terminal_id) REFERENCES terminales(id),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alternativas confirmadas que ve un cliente (caso OLEGA) y su selección (RF-09)
CREATE TABLE alternativas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  grupo VARCHAR(80) NOT NULL,
  carga VARCHAR(60) NULL,
  etiqueta CHAR(1) NOT NULL,
  naviera_id INT UNSIGNED NULL,
  buque VARCHAR(120) NULL,
  via VARCHAR(60) NOT NULL DEFAULT 'Directa',
  etd DATE NULL,
  eta DATE NULL,
  cutoff_doc DATETIME NULL,
  flete DECIMAL(10,2) NOT NULL DEFAULT 0,
  dias_libres SMALLINT UNSIGNED NULL,
  estado ENUM('disponible','elegida','no_elegida') NOT NULL DEFAULT 'disponible',
  elegida_por INT UNSIGNED NULL,
  elegida_en DATETIME NULL,
  comentario TEXT NULL,
  operacion_id INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_alt_grupo (cliente_id, grupo),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (naviera_id) REFERENCES navieras(id),
  FOREIGN KEY (elegida_por) REFERENCES usuarios(id),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Destinatarios de avisos elegidos para cada operación (SS5)
CREATE TABLE operacion_destinatarios (
  operacion_id INT UNSIGNED NOT NULL,
  participante_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (operacion_id, participante_id),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (participante_id) REFERENCES participantes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SS3 · Seguimiento operativo ----------

CREATE TABLE contenedores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operacion_id INT UNSIGNED NOT NULL,
  numero VARCHAR(20) NOT NULL,
  tipo VARCHAR(20) NULL,
  retiro_fecha DATE NULL,
  retiro_lugar VARCHAR(80) NULL,
  peso_bruto INT UNSIGNED NULL,
  vgm ENUM('pendiente','declarado','transmitido') NOT NULL DEFAULT 'pendiente',
  precinto VARCHAR(30) NULL,
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historial de modificaciones: dato anterior, dato nuevo, fecha, usuario y origen (RF-15)
CREATE TABLE historial_cambios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operacion_id INT UNSIGNED NOT NULL,
  dato VARCHAR(80) NOT NULL,
  valor_anterior VARCHAR(200) NULL,
  valor_nuevo VARCHAR(200) NULL,
  origen VARCHAR(80) NULL,
  detalle TEXT NULL,
  usuario_id INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_op (operacion_id),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SS4 · Gestión documental ----------
-- Los archivos físicos se guardan en la carpeta almacen/ (depósito de documentos)
-- y esta tabla guarda su referencia, versión y estado.

CREATE TABLE documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operacion_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(60) NOT NULL,
  version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('solicitado','recibido','revisado','presentado') NOT NULL DEFAULT 'recibido',
  origen VARCHAR(80) NULL,
  archivo_nombre VARCHAR(200) NULL,
  archivo_ruta VARCHAR(300) NULL,
  observaciones TEXT NULL,
  visible_cliente TINYINT(1) NOT NULL DEFAULT 1,
  subido_por INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_doc_op (operacion_id),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (subido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SS5 · Comunicaciones y avisos ----------

CREATE TABLE comunicaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operacion_id INT UNSIGNED NULL,
  cambio_id INT UNSIGNED NULL,
  evento VARCHAR(120) NOT NULL,
  tipo ENUM('cambio','seleccion','recordatorio','booking','documento','zarpe','cancelacion','solicitud','otro') NOT NULL DEFAULT 'otro',
  participante_id INT UNSIGNED NULL,
  destinatario_nombre VARCHAR(120) NOT NULL,
  destinatario_correo VARCHAR(160) NOT NULL,
  destinatario_tipo ENUM('cliente','despachante','interno','otro') NOT NULL DEFAULT 'cliente',
  asunto VARCHAR(200) NOT NULL,
  cuerpo TEXT NULL,
  estado_envio ENUM('enviado','fallido') NOT NULL,
  error VARCHAR(250) NULL,
  requiere_notado TINYINT(1) NOT NULL DEFAULT 0,
  plazo_notado DATETIME NULL,
  notado_en DATETIME NULL,
  notado_por INT UNSIGNED NULL,
  recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
  reenvio_de INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_com_op (operacion_id),
  KEY idx_com_notado (requiere_notado, notado_en),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (cambio_id) REFERENCES historial_cambios(id) ON DELETE SET NULL,
  FOREIGN KEY (notado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- SS2 · Disponibilidad de espacio y pedidos de reserva de los clientes ----------

-- Espacio que ARPAFLU tiene disponible en cada embarque (cupo de contenedores). Un cliente solo puede pedir lugar si hay cupo libre.
CREATE TABLE disponibilidad (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  naviera_id INT UNSIGNED NULL,
  buque VARCHAR(120) NOT NULL,
  via ENUM('directa','transbordo') NOT NULL DEFAULT 'directa',
  pol VARCHAR(80) NOT NULL DEFAULT 'Buenos Aires',
  pod VARCHAR(80) NOT NULL,
  etd DATE NULL,
  eta DATE NULL,
  cutoff_doc DATETIME NULL,
  tipo_contenedor VARCHAR(20) NOT NULL DEFAULT "40' HC",
  cupo SMALLINT UNSIGNED NOT NULL,
  flete DECIMAL(10,2) NOT NULL DEFAULT 0,
  dias_libres SMALLINT UNSIGNED NULL,
  estado ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  creado_por INT UNSIGNED NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_disp_estado (estado, cutoff_doc),
  FOREIGN KEY (naviera_id) REFERENCES navieras(id),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pedidos de reserva de los clientes sobre un espacio disponible. Un pedido pendiente o aceptado ocupa cupo.
CREATE TABLE solicitudes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  disponibilidad_id INT UNSIGNED NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL,
  carga VARCHAR(120) NULL,
  comentario TEXT NULL,
  estado ENUM('pendiente','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  motivo_rechazo VARCHAR(250) NULL,
  operacion_id INT UNSIGNED NULL,
  respondido_por INT UNSIGNED NULL,
  respondido_en DATETIME NULL,
  visto_cliente DATETIME NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sol_estado (estado),
  KEY idx_sol_disp (disponibilidad_id, estado),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (disponibilidad_id) REFERENCES disponibilidad(id),
  FOREIGN KEY (operacion_id) REFERENCES operaciones(id),
  FOREIGN KEY (respondido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estado de la demostración guiada (solo para pruebas)
CREATE TABLE demo (
  clave VARCHAR(40) NOT NULL PRIMARY KEY,
  valor VARCHAR(250) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
