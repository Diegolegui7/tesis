-- =========================================================
-- ARPAFLU · Datos de ejemplo para la demostración
-- Las fechas son relativas a "hoy" (CURDATE / NOW), así los
-- vencimientos y avisos siempre se ven actuales.
-- {{HASH}} lo reemplaza instalar.php por el hash de la clave de prueba.
-- Todo es ficticio.
-- =========================================================

SET NAMES utf8mb4;

-- ---------- Referencias ----------
INSERT INTO navieras (id, nombre) VALUES (1,'MSC'),(2,'Maersk'),(3,'Hapag-Lloyd'),(4,'CMA CGM'),(5,'ONE');

INSERT INTO buques (naviera_id, nombre, bandera) VALUES
 (1,'MSC Aurora V.642S','Panamá'),(1,'MSC Lena V.638S','Panamá'),(1,'MSC Lena V.644S','Panamá'),(1,'MSC Clara V.640S','Liberia'),
 (2,'Maersk Lirquén 243N','Dinamarca'),(2,'Maersk Lota 244N','Dinamarca'),
 (3,'Santa Teresa 2541N','Alemania'),(3,'Cap San Marco 2542N','Alemania'),(3,'Cap San Lorenzo 2544N','Alemania');

INSERT INTO puertos (nombre, es_origen) VALUES
 ('Buenos Aires',1),('Zárate',1),('Rosario',1),
 ('Rotterdam',0),('Hamburgo',0),('Valencia',0),('Santos',0),('Callao',0),('Shanghái',0),('Nueva York',0);

INSERT INTO terminales (id, nombre) VALUES (1,'Terminal 4 (TRP)'),(2,'Exolgan'),(3,'BACTSSA');

-- ---------- Clientes (sin responsable todavía) ----------
INSERT INTO clientes (id, empresa, cuit, contacto, correo, despachante, correo_despachante, canal, selecciona_alternativas) VALUES
 (1,'OLEGA S.A.','30-70123456-7','Lucía Ferreyra','lferreyra@olega.com.ar','Aduanas Rivas','operaciones@aduanasrivas.com.ar','correo',1),
 (2,'Agroexport Sur','30-71234567-8','Martín Gómez','mgomez@agroexportsur.com','Despachos Litoral','info@despacholitoral.com.ar','correo',0),
 (3,'Frigorífico Del Sur','30-68345678-9','Ana Benítez','abenitez@frigodelsur.com','Estudio Aduanero Paz','paz@estudiopaz.com.ar','whatsapp',0),
 (4,'Molinos Pampa','30-69456789-0','Diego Herrera','dherrera@molinospampa.com','Aduanas Rivas','operaciones@aduanasrivas.com.ar','correo',0),
 (5,'Vinos de Cuyo SRL','30-70567890-1','Sofía Molina','smolina@vinosdecuyo.com','Despachos Andinos','mesa@despachosandinos.com.ar','correo',0);

-- ---------- Usuarios ----------
INSERT INTO usuarios (id, nombre, correo, clave_hash, tipo, rol, cliente_id, puede_seleccionar, activo, ultimo_ingreso) VALUES
 (1,'Ricardo Paz','rpaz@arpaflu.com','{{HASH}}','empleado','administrador',NULL,0,1,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (2,'Marina Sosa','msosa@arpaflu.com','{{HASH}}','empleado','operaciones',NULL,0,1,DATE_SUB(NOW(), INTERVAL 4 HOUR)),
 (3,'Pablo Ruiz','pruiz@arpaflu.com','{{HASH}}','empleado','operaciones',NULL,0,1,DATE_SUB(NOW(), INTERVAL 4 HOUR)),
 (4,'Carla Méndez','cmendez@arpaflu.com','{{HASH}}','empleado','comercial',NULL,0,1,DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (5,'Julián Ortega','jortega@arpaflu.com','{{HASH}}','empleado','consulta',NULL,0,0,DATE_SUB(NOW(), INTERVAL 11 DAY)),
 (6,'Lucía Ferreyra','lferreyra@olega.com.ar','{{HASH}}','cliente','cliente',1,1,1,DATE_SUB(NOW(), INTERVAL 3 HOUR)),
 (7,'Tomás Aguirre','taguirre@olega.com.ar','{{HASH}}','cliente','cliente',1,0,1,DATE_SUB(NOW(), INTERVAL 3 DAY)),
 (8,'Martín Gómez','mgomez@agroexportsur.com','{{HASH}}','cliente','cliente',2,1,1,DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (9,'Ana Benítez','abenitez@frigodelsur.com','{{HASH}}','cliente','cliente',3,1,1,NULL),
 (10,'Diego Herrera','dherrera@molinospampa.com','{{HASH}}','cliente','cliente',4,1,1,DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (11,'Sofía Molina','smolina@vinosdecuyo.com','{{HASH}}','cliente','cliente',5,1,1,DATE_SUB(NOW(), INTERVAL 1 DAY));

UPDATE clientes SET responsable_id = 2 WHERE id = 1;
UPDATE clientes SET responsable_id = 3 WHERE id IN (2,3);
UPDATE clientes SET responsable_id = 4 WHERE id IN (4,5);

-- Asignaciones y permisos por cliente
INSERT INTO asignaciones (cliente_id, usuario_id, desde) VALUES
 (1,2,DATE_SUB(CURDATE(), INTERVAL 200 DAY)),(2,3,DATE_SUB(CURDATE(), INTERVAL 200 DAY)),(3,3,DATE_SUB(CURDATE(), INTERVAL 200 DAY)),
 (4,4,DATE_SUB(CURDATE(), INTERVAL 200 DAY)),(5,4,DATE_SUB(CURDATE(), INTERVAL 200 DAY));

INSERT INTO permisos_cliente (usuario_id, cliente_id, nivel) VALUES
 (2,1,'gestion'),(3,2,'gestion'),(3,3,'gestion'),(4,4,'gestion'),(4,5,'gestion'),
 (2,2,'consulta'),(2,3,'consulta'),(2,4,'consulta'),(2,5,'consulta'),
 (3,1,'consulta'),(3,4,'consulta'),(3,5,'consulta');

-- Permisos por función (los carga la matriz de roles; se pueden editar por usuario)
INSERT INTO permisos (usuario_id, funcion, permitido)
SELECT u.id, f.funcion,
  CASE
    WHEN u.rol = 'administrador' THEN 1
    WHEN u.rol = 'operaciones' AND f.funcion IN ('ver_operaciones','crear_operaciones','cotizaciones','costos_internos','cargar_documentos','registrar_cambios','ver_historial') THEN 1
    WHEN u.rol = 'comercial' AND f.funcion IN ('ver_operaciones','cotizaciones','costos_internos') THEN 1
    WHEN u.rol = 'consulta' AND f.funcion IN ('ver_operaciones') THEN 1
    ELSE 0
  END
FROM usuarios u
JOIN (SELECT 'ver_operaciones' AS funcion UNION SELECT 'crear_operaciones' UNION SELECT 'cotizaciones' UNION SELECT 'costos_internos'
      UNION SELECT 'cargar_documentos' UNION SELECT 'registrar_cambios' UNION SELECT 'ver_historial' UNION SELECT 'administrar_usuarios') f
WHERE u.tipo = 'empleado';

-- ---------- Participantes (destinatarios de avisos) ----------
INSERT INTO participantes (id, cliente_id, nombre, correo, rol) VALUES
 (1,1,'Lucía Ferreyra','lferreyra@olega.com.ar','contacto'),
 (2,1,'Aduanas Rivas','operaciones@aduanasrivas.com.ar','despachante'),
 (3,2,'Martín Gómez','mgomez@agroexportsur.com','contacto'),
 (4,2,'Despachos Litoral','info@despacholitoral.com.ar','despachante'),
 (5,3,'Ana Benítez','abenitez@frigodelsur.com','contacto'),
 (6,3,'Estudio Aduanero Paz','paz@estudiopaz.com.ar','despachante'),
 (7,3,'Compras Frigorífico Del Sur','compras@frigodelsur.invalid','otro'),
 (8,4,'Diego Herrera','dherrera@molinospampa.com','contacto'),
 (9,4,'Aduanas Rivas','operaciones@aduanasrivas.com.ar','despachante'),
 (10,5,'Sofía Molina','smolina@vinosdecuyo.com','contacto'),
 (11,5,'Despachos Andinos','mesa@despachosandinos.com.ar','despachante');

-- ---------- Cotizaciones ----------
INSERT INTO cotizaciones (id, codigo, version, cliente_id, naviera_id, pod, contenedores, flete, costo_interno, condiciones, vigencia, estado, creado_por, creado) VALUES
 (1,'COT-0381',1,2,3,'Santos',"1 × 20' DV",890,640,'Días libres: 7. Sin recargos.',DATE_SUB(CURDATE(), INTERVAL 15 DAY),'vencida',3,DATE_SUB(NOW(), INTERVAL 30 DAY)),
 (2,'COT-0385',1,5,2,'Nueva York',"1 × 40' HC",3020,2500,'Días libres: 10.',DATE_SUB(CURDATE(), INTERVAL 13 DAY),'rechazada',4,DATE_SUB(NOW(), INTERVAL 28 DAY)),
 (3,'COT-0388',1,1,1,'Rotterdam',"2 × 40' HC",2280,1900,'Días libres: 14. BAF incluido.',DATE_SUB(CURDATE(), INTERVAL 8 DAY),'reemplazada',2,DATE_SUB(NOW(), INTERVAL 14 DAY)),
 (4,'COT-0388',2,1,1,'Rotterdam',"2 × 40' HC",2150,1900,'Días libres: 14. BAF incluido.',DATE_ADD(CURDATE(), INTERVAL 5 DAY),'aceptada',2,DATE_SUB(NOW(), INTERVAL 10 DAY)),
 (5,'COT-0390',1,3,4,'Shanghái',"2 × 40' RF",4350,3700,'Reefer: temperatura a definir por el cliente.',DATE_ADD(CURDATE(), INTERVAL 7 DAY),'aceptada',3,DATE_SUB(NOW(), INTERVAL 9 DAY)),
 (6,'COT-0391',1,1,1,'Rotterdam',"2 × 40' HC",2190,1900,'Días libres: 14.',DATE_ADD(CURDATE(), INTERVAL 12 DAY),'enviada',2,DATE_SUB(NOW(), INTERVAL 3 DAY)),
 (7,'COT-0393',1,4,5,'Callao',"4 × 20' DV",1520,1210,'Días libres: 7.',DATE_ADD(CURDATE(), INTERVAL 16 DAY),'reemplazada',4,DATE_SUB(NOW(), INTERVAL 6 DAY)),
 (8,'COT-0393',2,4,5,'Callao',"4 × 20' DV",1480,1210,'Días libres: 7.',DATE_ADD(CURDATE(), INTERVAL 18 DAY),'aceptada',4,DATE_SUB(NOW(), INTERVAL 4 DAY));

-- ---------- Operaciones ----------
INSERT INTO operaciones (id, referencia, cliente_id, referencia_cliente, cotizacion_id, responsable_id, naviera_id, buque, booking, semana, pol, pod, via, terminal_id,
  cantidad, tipo_contenedor, dias_libres, etd, eta, cutoff_doc, cutoff_fisico, vgm_limite, booking_confirmado_en, orden_retiro, orden_retiro_fecha, estado, zarpe_confirmado, creado_por, creado) VALUES
 (1,'ARP-0408',1,'OLG-EXP-1181',NULL,2,1,'MSC Clara V.640S','254AR0090811',WEEK(CURDATE())-1,'Buenos Aires','Hamburgo','directa',1,
  2,"40' HC",14,DATE_SUB(CURDATE(), INTERVAL 3 DAY),DATE_ADD(CURDATE(), INTERVAL 19 DAY),TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY),'12:00:00'),TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY),'18:00:00'),NULL,
  DATE_SUB(NOW(), INTERVAL 20 DAY),'OR-MSC-54802',DATE_SUB(CURDATE(), INTERVAL 10 DAY),'zarpado',DATE_SUB(NOW(), INTERVAL 3 DAY),2,DATE_SUB(NOW(), INTERVAL 24 DAY)),
 (2,'ARP-0412',1,'OLG-EXP-1187',4,2,1,'MSC Aurora V.642S','254AR0091234',WEEK(CURDATE()),'Buenos Aires','Rotterdam','directa',1,
  2,"40' HC",14,DATE_ADD(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 27 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY),'12:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY),'18:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY),'10:00:00'),
  DATE_SUB(NOW(), INTERVAL 11 DAY),'OR-MSC-55102',DATE_SUB(CURDATE(), INTERVAL 6 DAY),'documentacion',NULL,2,DATE_SUB(NOW(), INTERVAL 13 DAY)),
 (3,'ARP-0415',2,'AGX-5521',NULL,3,3,'Santa Teresa 2541N','HLCUBA2610078',WEEK(CURDATE()),'Buenos Aires','Santos','directa',2,
  1,"20' DV",7,DATE_ADD(CURDATE(), INTERVAL 4 DAY),DATE_ADD(CURDATE(), INTERVAL 9 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY),'12:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY),'18:00:00'),NULL,
  DATE_SUB(NOW(), INTERVAL 8 DAY),'OR-HL-77120',DATE_SUB(CURDATE(), INTERVAL 2 DAY),'orden_retiro',NULL,3,DATE_SUB(NOW(), INTERVAL 10 DAY)),
 (4,'ARP-0416',5,'VDC-0932',NULL,4,3,'Cap San Marco 2542N','HLCUBA2610102',WEEK(CURDATE()),'Buenos Aires','Nueva York','directa',3,
  1,"40' HC",10,DATE_ADD(CURDATE(), INTERVAL 6 DAY),DATE_ADD(CURDATE(), INTERVAL 24 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY),'12:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY),'18:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY),'10:00:00'),
  DATE_SUB(NOW(), INTERVAL 6 DAY),NULL,NULL,'documentacion',NULL,4,DATE_SUB(NOW(), INTERVAL 8 DAY)),
 (5,'ARP-0418',1,'OLG-EXP-1189',NULL,2,2,'Maersk Lirquén 243N','MAEU264110055',WEEK(CURDATE())+2,'Buenos Aires','Valencia','directa',1,
  3,"40' HC",12,DATE_ADD(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 31 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY),'12:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 9 DAY),'18:00:00'),NULL,
  DATE_SUB(NOW(), INTERVAL 1 DAY),NULL,NULL,'booking_confirmado',NULL,2,DATE_SUB(NOW(), INTERVAL 5 DAY)),
 (6,'ARP-0420',3,'FDS-0077',5,3,4,'CMA CGM Tigre 0FL4EW1MA','CMDU264400318',WEEK(CURDATE())+1,'Buenos Aires','Shanghái','transbordo',2,
  2,"40' RF",21,DATE_ADD(CURDATE(), INTERVAL 11 DAY),DATE_ADD(CURDATE(), INTERVAL 48 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY),'12:00:00'),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY),'18:00:00'),NULL,
  DATE_SUB(NOW(), INTERVAL 4 DAY),NULL,NULL,'booking_confirmado',NULL,3,DATE_SUB(NOW(), INTERVAL 6 DAY)),
 (7,'ARP-0421',4,'MPA-3310',8,4,5,NULL,NULL,WEEK(CURDATE())+2,'Buenos Aires','Callao','directa',NULL,
  4,"20' DV",7,NULL,NULL,NULL,NULL,NULL,
  NULL,NULL,NULL,'reserva_solicitada',NULL,4,DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO operacion_destinatarios (operacion_id, participante_id) VALUES
 (1,1),(1,2),(2,1),(2,2),(3,3),(3,4),(4,10),(4,11),(5,1),(5,2),(6,5),(6,6),(6,7),(7,8),(7,9);

INSERT INTO contenedores (operacion_id, numero, tipo, retiro_fecha, retiro_lugar, peso_bruto, vgm, precinto) VALUES
 (1,'MSCU 701100-4',"40' HC",DATE_SUB(CURDATE(), INTERVAL 8 DAY),'Depósito Dock Sud',23900,'transmitido','MS4400812'),
 (1,'MSCU 701215-9',"40' HC",DATE_SUB(CURDATE(), INTERVAL 8 DAY),'Depósito Dock Sud',24120,'transmitido','MS4400813'),
 (2,'MSCU 718204-3',"40' HC",DATE_SUB(CURDATE(), INTERVAL 3 DAY),'Depósito Dock Sud',24380,'declarado','MS4471209'),
 (2,'MSCU 720551-8',"40' HC",DATE_SUB(CURDATE(), INTERVAL 3 DAY),'Depósito Dock Sud',NULL,'pendiente',NULL),
 (3,'HLXU 330412-0',"20' DV",DATE_SUB(CURDATE(), INTERVAL 1 DAY),'Depósito Barracas',NULL,'pendiente',NULL);

-- ---------- Documentos (los de ejemplo no tienen archivo adjunto) ----------
INSERT INTO documentos (operacion_id, tipo, version, estado, origen, observaciones, visible_cliente, subido_por, creado) VALUES
 (2,'Confirmación de booking',1,'recibido','MSC',NULL,1,2,DATE_SUB(NOW(), INTERVAL 11 DAY)),
 (2,'Orden de retiro',1,'recibido','MSC',NULL,1,2,DATE_SUB(NOW(), INTERVAL 6 DAY)),
 (2,'Declaración (DECLA)',1,'revisado','OLEGA',NULL,1,2,DATE_SUB(NOW(), INTERVAL 3 DAY)),
 (2,'DRAFT',1,'recibido','MSC','Observado por el cliente: puerto de destino incorrecto',0,2,DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (2,'DRAFT',2,'recibido','MSC','Corrige el puerto de destino de la v1',1,2,DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (2,'Ticket de pesada',1,'recibido','Terminal 4','Solo contenedor MSCU 718204-3',1,2,DATE_SUB(NOW(), INTERVAL 20 HOUR)),
 (2,'VGM',1,'solicitado','OLEGA','Falta el segundo contenedor',1,2,DATE_SUB(NOW(), INTERVAL 3 DAY)),
 (3,'Confirmación de booking',1,'recibido','Hapag-Lloyd',NULL,1,3,DATE_SUB(NOW(), INTERVAL 8 DAY)),
 (3,'Orden de retiro',1,'recibido','Hapag-Lloyd',NULL,1,3,DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (4,'DRAFT',1,'recibido','Hapag-Lloyd','La naviera pidió corregir el peso bruto',1,4,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (5,'Confirmación de booking',1,'recibido','Maersk',NULL,1,2,DATE_SUB(NOW(), INTERVAL 1 DAY)),
 (1,'Confirmación de booking',1,'recibido','MSC',NULL,1,2,DATE_SUB(NOW(), INTERVAL 20 DAY));

-- ---------- Historial de cambios y avisos ----------
INSERT INTO historial_cambios (id, operacion_id, dato, valor_anterior, valor_nuevo, origen, usuario_id, creado) VALUES
 (1,2,'Roleo: cambio de buque','MSC Lena V.638S','MSC Aurora V.642S','Correo de la naviera',2,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (2,2,'ETD',DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY),'%d/%m/%Y'),DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY),'%d/%m/%Y'),'Correo de la naviera',2,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (3,2,'Cut-off documental',DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY),'%d/%m/%Y 12:00'),DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY),'%d/%m/%Y 12:00'),'Correo de la naviera',2,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (4,6,'ETD',DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 9 DAY),'%d/%m/%Y'),DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 11 DAY),'%d/%m/%Y'),'Portal de la naviera',3,DATE_SUB(NOW(), INTERVAL 22 HOUR)),
 (5,3,'Terminal','Terminal 4 (TRP)','Exolgan','Terminal',3,DATE_SUB(NOW(), INTERVAL 26 HOUR)),
 (6,4,'Cut-off físico',DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 4 DAY),'%d/%m/%Y 18:00'),DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY),'%d/%m/%Y 18:00'),'Correo de la naviera',4,DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO comunicaciones (operacion_id, cambio_id, evento, tipo, destinatario_nombre, destinatario_correo, destinatario_tipo, asunto, cuerpo, estado_envio, error, requiere_notado, plazo_notado, notado_en, notado_por, creado) VALUES
 (2,1,'Cambio de buque y fechas','cambio','Lucía Ferreyra','lferreyra@olega.com.ar','cliente','ARP-0412 · Cambio de buque y fechas','La naviera roleó la carga: el buque pasó de MSC Lena V.638S a MSC Aurora V.642S.','enviado',NULL,1,DATE_SUB(NOW(), INTERVAL 1 HOUR),DATE_SUB(NOW(), INTERVAL 4 HOUR),6,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (2,1,'Cambio de buque y fechas','cambio','Aduanas Rivas','operaciones@aduanasrivas.com.ar','despachante','ARP-0412 · Cambio de buque y fechas','La naviera roleó la carga: el buque pasó de MSC Lena V.638S a MSC Aurora V.642S.','enviado',NULL,1,DATE_SUB(NOW(), INTERVAL 1 HOUR),NULL,NULL,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (2,1,'Cambio de buque y fechas','cambio','Marina Sosa','msosa@arpaflu.com','interno','ARP-0412 · Cambio de buque y fechas','Se registró el cambio de buque y fechas.','enviado',NULL,0,NULL,NULL,NULL,DATE_SUB(NOW(), INTERVAL 5 HOUR)),
 (2,NULL,'Recordatorio: VGM pendiente','recordatorio','Lucía Ferreyra','lferreyra@olega.com.ar','cliente','ARP-0412 · Falta el VGM del segundo contenedor','Recordatorio: falta el VGM del contenedor MSCU 720551-8.','enviado',NULL,0,NULL,NULL,NULL,DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (2,NULL,'Booking confirmado','booking','Lucía Ferreyra','lferreyra@olega.com.ar','cliente','ARP-0412 · Booking confirmado','Booking 254AR0091234 confirmado por MSC.','enviado',NULL,0,NULL,NULL,NULL,DATE_SUB(NOW(), INTERVAL 11 DAY)),
 (6,4,'Cambio de ETD','cambio','Ana Benítez','abenitez@frigodelsur.com','cliente','ARP-0420 · Cambio de ETD','El zarpe estimado se movió dos días.','enviado',NULL,1,DATE_SUB(NOW(), INTERVAL 2 HOUR),NULL,NULL,DATE_SUB(NOW(), INTERVAL 22 HOUR)),
 (6,4,'Cambio de ETD','cambio','Compras Frigorífico Del Sur','compras@frigodelsur.invalid','otro','ARP-0420 · Cambio de ETD','El zarpe estimado se movió dos días.','fallido','El servidor de correo rechazó la dirección',0,NULL,NULL,NULL,DATE_SUB(NOW(), INTERVAL 22 HOUR)),
 (3,5,'Cambio de terminal','cambio','Despachos Litoral','info@despacholitoral.com.ar','despachante','ARP-0415 · Cambio de terminal','La terminal pasó de Terminal 4 (TRP) a Exolgan.','enviado',NULL,1,DATE_SUB(NOW(), INTERVAL 20 HOUR),DATE_SUB(NOW(), INTERVAL 25 HOUR),8,DATE_SUB(NOW(), INTERVAL 26 HOUR)),
 (4,6,'Cambio de cut-off físico','cambio','Sofía Molina','smolina@vinosdecuyo.com','cliente','ARP-0416 · Cambio de cut-off físico','El cut-off físico se movió un día.','enviado',NULL,1,DATE_ADD(NOW(), INTERVAL 2 HOUR),NULL,NULL,DATE_SUB(NOW(), INTERVAL 2 HOUR)),
 (3,NULL,'Recordatorio: cut-off documental','recordatorio','Martín Gómez','mgomez@agroexportsur.com','cliente','ARP-0415 · Vence el cut-off documental','Recordatorio: el cut-off documental vence en las próximas 48 horas.','enviado',NULL,0,NULL,NULL,NULL,DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- Vincula los avisos de ejemplo con el participante (para reenviar al correo vigente)
UPDATE comunicaciones c JOIN operaciones o ON o.id = c.operacion_id JOIN participantes p ON p.cliente_id = o.cliente_id AND p.correo = c.destinatario_correo SET c.participante_id = p.id;

-- ---------- Alternativas (caso OLEGA) ----------
-- Grupo ya resuelto: OLEGA eligió la B y se creó ARP-0418
INSERT INTO alternativas (cliente_id, grupo, carga, etiqueta, naviera_id, buque, via, etd, eta, cutoff_doc, flete, dias_libres, estado, elegida_por, elegida_en, operacion_id) VALUES
 (1,'Semana de embarque anterior · Buenos Aires → Valencia',"3 × 40' HC",'A',1,'MSC Lena V.644S','Directa',DATE_ADD(CURDATE(), INTERVAL 10 DAY),DATE_ADD(CURDATE(), INTERVAL 30 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY),'12:00:00'),2200,14,'no_elegida',NULL,NULL,NULL),
 (1,'Semana de embarque anterior · Buenos Aires → Valencia',"3 × 40' HC",'B',2,'Maersk Lirquén 243N','Directa',DATE_ADD(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 31 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY),'12:00:00'),2090,12,'elegida',6,DATE_SUB(NOW(), INTERVAL 3 HOUR),5),
 (1,'Semana de embarque anterior · Buenos Aires → Valencia',"3 × 40' HC",'C',3,'Cap San Lorenzo 2544N','Directa',DATE_ADD(CURDATE(), INTERVAL 13 DAY),DATE_ADD(CURDATE(), INTERVAL 33 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 9 DAY),'12:00:00'),2260,21,'no_elegida',NULL,NULL,NULL);

-- Grupo abierto: OLEGA tiene que elegir
INSERT INTO alternativas (cliente_id, grupo, carga, etiqueta, naviera_id, buque, via, etd, eta, cutoff_doc, flete, dias_libres, estado) VALUES
 (1,'Próximo embarque · Buenos Aires → Rotterdam',"2 × 40' HC",'A',1,'MSC Lena V.644S','Directa',DATE_ADD(CURDATE(), INTERVAL 18 DAY),DATE_ADD(CURDATE(), INTERVAL 40 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 14 DAY),'12:00:00'),2190,14,'disponible'),
 (1,'Próximo embarque · Buenos Aires → Rotterdam',"2 × 40' HC",'B',2,'Maersk Lota 244N','Transbordo en Santos',DATE_ADD(CURDATE(), INTERVAL 16 DAY),DATE_ADD(CURDATE(), INTERVAL 44 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 12 DAY),'12:00:00'),2040,10,'disponible'),
 (1,'Próximo embarque · Buenos Aires → Rotterdam',"2 × 40' HC",'C',3,'Cap San Lorenzo 2544N','Directa',DATE_ADD(CURDATE(), INTERVAL 15 DAY),DATE_ADD(CURDATE(), INTERVAL 36 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 11 DAY),'12:00:00'),2260,21,'disponible');

-- Aviso interno por la selección ya hecha
INSERT INTO comunicaciones (operacion_id, evento, tipo, destinatario_nombre, destinatario_correo, destinatario_tipo, asunto, cuerpo, estado_envio, requiere_notado, creado) VALUES
 (5,'Selección de alternativa','seleccion','Marina Sosa','msosa@arpaflu.com','interno','ARP-0418 · OLEGA eligió la alternativa B','Lucía Ferreyra (OLEGA) eligió la alternativa B: Maersk Lirquén 243N.','enviado',0,DATE_SUB(NOW(), INTERVAL 3 HOUR));

-- ---------- Cliente nuevo (sin reservas) para la demostración guiada ----------
INSERT INTO clientes (id, empresa, cuit, contacto, correo, despachante, correo_despachante, canal, selecciona_alternativas, responsable_id) VALUES
 (6,'Exportadora Nueva S.R.L.','30-71888999-0','Carlos Nuevo','cnuevo@exportadoranueva.com','Despachos del Puerto','mesa@despachosdelpuerto.com.ar','correo',0,2);
INSERT INTO usuarios (id, nombre, correo, clave_hash, tipo, rol, cliente_id, puede_seleccionar, activo, ultimo_ingreso) VALUES
 (12,'Carlos Nuevo','cnuevo@exportadoranueva.com','{{HASH}}','cliente','cliente',6,1,1,NULL);
INSERT INTO participantes (id, cliente_id, nombre, correo, rol) VALUES
 (12,6,'Carlos Nuevo','cnuevo@exportadoranueva.com','contacto'),
 (13,6,'Despachos del Puerto','mesa@despachosdelpuerto.com.ar','despachante');
INSERT INTO asignaciones (cliente_id, usuario_id, desde) VALUES (6,2,CURDATE());
INSERT INTO permisos_cliente (usuario_id, cliente_id, nivel) VALUES (2,6,'gestion'),(3,6,'consulta'),(4,6,'consulta');

-- ---------- Disponibilidad de espacio (lo que un cliente puede pedir) ----------
INSERT INTO disponibilidad (id, naviera_id, buque, via, pol, pod, etd, eta, cutoff_doc, tipo_contenedor, cupo, flete, dias_libres, creado_por) VALUES
 (1,1,'MSC Diletta V.650S','directa','Buenos Aires','Rotterdam',DATE_ADD(CURDATE(), INTERVAL 14 DAY),DATE_ADD(CURDATE(), INTERVAL 38 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 10 DAY),'12:00:00'),"40' HC",6,2190,14,2),
 (2,2,'Maersk Lota 246N','transbordo','Buenos Aires','Valencia',DATE_ADD(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 40 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY),'12:00:00'),"40' HC",4,2040,10,2),
 (3,3,'Cap San Lorenzo 2548N','directa','Buenos Aires','Hamburgo',DATE_ADD(CURDATE(), INTERVAL 16 DAY),DATE_ADD(CURDATE(), INTERVAL 36 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 12 DAY),'12:00:00'),"40' HC",2,2260,21,2),
 (4,4,'CMA CGM Tigre 0FL5','directa','Buenos Aires','Santos',DATE_ADD(CURDATE(), INTERVAL 9 DAY),DATE_ADD(CURDATE(), INTERVAL 14 DAY),TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY),'12:00:00'),"20' DV",3,890,7,3);

-- Un pedido de otro cliente deja sin cupo el embarque a Santos y queda pendiente de respuesta
INSERT INTO solicitudes (cliente_id, usuario_id, disponibilidad_id, cantidad, carga, comentario, estado, creado) VALUES
 (2,8,4,3,'Cebada en bolsas','Si es posible, retiro del vacío a primera hora.','pendiente',DATE_SUB(NOW(), INTERVAL 3 HOUR));
