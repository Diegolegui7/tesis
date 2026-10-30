# ARPAFLU · Sistema de seguimiento de reservas (versión de prueba)

Prototipo funcional del sistema definido en el Diseño Global. **Es para pruebas y demostración en una computadora local**, no para producción.

- Pantallas: HTML, CSS y JavaScript (las armó el equipo de interfaz).
- Lógica: PHP (compatible con PHP 7.3, el que trae WAMP).
- Datos: MySQL 5.7 / MariaDB 10.3 (también los de WAMP).
- Depósito de documentos: carpeta `almacen/`.

## Demostración guiada paso a paso (la forma más fácil)

1. Abrir WAMP y esperar el ícono **verde** (si no está abierto, el archivo lo intenta abrir).
2. Doble clic en **`INICIAR_DEMO.bat`** (está dentro de esta misma carpeta `arpaflu`, así que viaja con el repositorio; también funciona desde donde se haya clonado). Carga los datos de ejemplo en la base `arpaflu`, prende un servidor local en el puerto 8088 y abre el navegador.
3. En el navegador aparece un **panel naranja** abajo a la derecha con el paso actual (13 pasos). El panel se actualiza solo cuando se hace lo que pide: entrar como cliente, pedir una reserva, entrar como personal, aceptar el pedido, etc. También se puede **saltear** un paso o **reiniciar la demo** (vuelve a cargar los datos de ejemplo de la base `arpaflu` local y empieza otra vez). Si alguien ocultó la guía, en la pantalla de ingreso está “Empezar la demostración guiada desde el principio”. Si se hace una acción fuera de orden, la guía se saltea sola los pasos que ya no se pueden hacer.
4. La misma guía se ve en la ventana negra del `.bat`.
   Cada vez que se ejecuta, el `.bat` también deja una copia actualizada del sistema en `C:\wamp64\www\arpaflu` (sin tocar `almacen`), así que con WAMP en verde se puede entrar por <http://localhost/arpaflu/> y ver lo mismo.
5. Al terminar, doble clic en **`DETENER_DEMO.bat`**.

Nota: si en la demo se cambia la clave de un usuario, el acceso rápido de ese usuario deja de funcionar (usa la clave de prueba).

## Cómo ponerlo a andar con WAMP (sin el .bat)

1. Copiar esta carpeta `arpaflu` dentro de `C:\wamp64\www\`.
2. Abrir WAMP y esperar a que el ícono esté en **verde** (Apache y MySQL funcionando).
3. En el navegador: <http://localhost/arpaflu/instalar.php> y apretar **Instalar / reiniciar con datos de ejemplo**. Crea la base `arpaflu`, las tablas y los datos de ejemplo.
4. Ir a <http://localhost/arpaflu/> e ingresar.

Si MySQL tiene clave de `root` (en WAMP viene vacía), cambiarla en `config.php`.
Para volver a dejar todo como al principio se repite el paso 3.

## Cuentas de prueba

La clave de todas las cuentas está en `config.php` (`CLAVE_DEMO`). En la pantalla de ingreso hay botones de acceso rápido.

| Cuenta | Qué permite |
|---|---|
| rpaz@arpaflu.com | Administrador: todo, incluso usuarios y permisos |
| msosa@arpaflu.com | Operaciones: actúa sobre OLEGA |
| pruiz@arpaflu.com | Operaciones: actúa sobre Agroexport Sur y Frigorífico Del Sur |
| cmendez@arpaflu.com | Comercial: cotizaciones |
| lferreyra@olega.com.ar | Cliente OLEGA, puede elegir alternativas |
| taguirre@olega.com.ar | Cliente OLEGA, solo consulta |
| mgomez@agroexportsur.com | Cliente Agroexport Sur |
| carlos (Exportadora Nueva S.R.L.) | Cliente nuevo, sin reservas: es el que usa la demo guiada para pedir su primera reserva |

## Guion para la demostración (unos 6 minutos)

1. **Marina** → Panel: vencimientos, “Notado” vencido, avisos que fallaron.
2. **Marina** → ARP-0412 → *Registrar cambio* → “Roleo” con buque, ETD y cut-off nuevos → *Guardar y avisar*. Se ve el historial (dato anterior → dato nuevo, fecha, usuario) y los avisos con su resultado.
3. **Lucía** (cliente) → ARP-0412 → botón **Notado**. Después, *Alternativas* → elegir una → queda registrada con usuario y fecha.
4. **Marina** → Avisos: llegó el aviso de la selección; “No enviados” → reenviar. Después, Alternativas: se ve la elegida con usuario y fecha y se puede cargar un grupo nuevo de alternativas para OLEGA.
5. **Marina** → Panel → *Ejecutar control de vencimientos* (proceso automático P9).
6. **Pablo** intenta modificar OLEGA: el sistema se lo impide (permisos por cliente). **Ricardo** → Usuarios y permisos.

## Vista simple del personal

Al entrar, el personal y el administrador ven **Inicio** (`personal/inicio.php`): una lista de lo que hay que hacer ahora (responder pedidos, cargar booking, cargar orden de retiro, avisar que el barco salió, avisos que no llegaron, plazos que vencen), accesos grandes y, para quien administra, un resumen “Cómo va el negocio”. El menú es corto (Inicio, Pedidos de clientes, Reservas, Lugar disponible, Clientes, Avisos enviados, Personas y permisos, Ayuda). Abajo a la izquierda, **Ver todas las opciones** pasa a la vista completa (Panel, Cotizaciones, Alternativas) y **Volver a la vista simple** regresa; ahí también están **Mi clave** y **Salir**.

La **ficha de cada reserva** es una pantalla guiada: el recuadro **Qué sigue** dice cuál es el único paso que corresponde (booking → orden de retiro → “El barco ya salió”) y trae el formulario con un botón grande; debajo, “Cómo va la reserva” muestra los cinco pasos y “Otras cosas que se pueden hacer” agrupa avisar un cambio, subir un documento y cancelar. El detalle queda en secciones plegables. Las palabras técnicas (booking, ETD, ETA, VGM…) se explican al pasar el mouse y en **Ayuda**.

## Pedir una reserva (portal de clientes)

- El personal carga **embarques con cupo** en *Disponibilidad* (`personal/disponibilidad.php`).
- El cliente habilitado entra a *Pedir una reserva* (`cliente/pedir.php`): solo puede completar el formulario si hay lugar libre, el embarque está abierto y no venció el plazo de documentos. Si no hay lugar, la pantalla lo explica y no deja pedir.
- El pedido queda *pendiente* y ocupa cupo (se controla con una transacción para que no se venda de más). El personal lo responde en *Pedidos de clientes* (`personal/pedidos.php`): **aceptar** crea la operación y avisa al cliente; **rechazar** exige un motivo y libera el lugar. El cliente también puede cancelar su pedido.
- El portal del cliente usa lenguaje simple: estados en palabras comunes, barra de 5 pasos, recuadro “Qué tenés que hacer ahora”, ayuda con glosario (`cliente/ayuda.php`), confirmaciones antes de acciones importantes y una guía de bienvenida. Hay *Mi clave* (cambio) y *Olvidé mi clave* (`recuperar.php`, que avisa a los administradores para que restablezcan la clave).
- Un cliente necesita el permiso “puede pedir reservas y elegir alternativas” (campo `puede_seleccionar`).

## Qué parte del Diseño Global cubre cada cosa

| Subsistema | Pantallas / archivos |
|---|---|
| SS1 Seguridad | `index.php`, `personal/usuarios.php`, tablas `usuarios`, `permisos`, `permisos_cliente`, `asignaciones` |
| SS2 Gestión comercial y de reservas | `personal/clientes.php`, `cotizaciones.php`, `nueva-operacion.php`, `personal/alternativas.php`, `cliente/alternativas.php`, tablas `clientes`, `cotizaciones`, `operaciones`, `alternativas` |
| SS3 Seguimiento operativo | `personal/operacion.php` (booking, orden de retiro, contenedores, cambios, zarpe, cancelación), `historial_cambios`, `contenedores` |
| SS4 Gestión documental | pestaña Documentos, `descargar.php`, tabla `documentos`, carpeta `almacen/` |
| SS5 Comunicaciones y avisos | `personal/avisos.php`, `inc/avisos.php`, “Notado” en `cliente/operacion.php`, tabla `comunicaciones` |
| SS6 Consultas e historial | `personal/panel.php`, `personal/operaciones.php`, historial de cada operación |

Procesos: P1 usuarios · P2 cotizaciones · P3 nueva operación · P4 booking · P5 selección OLEGA · P6 orden de retiro · P7 documentos y contenedores · P8 cambios y avisos · P9 vencimientos · P10 “Notado” · P11 cancelación · P12 zarpe · P13 consultas.

## Avisos por correo

Por defecto (`MAIL_MODO = 'simulado'`) **no sale ningún correo**: el aviso queda registrado en el sistema con su resultado. Las direcciones que terminan en `.invalid` se rechazan a propósito para mostrar cómo se registra un aviso no enviado.

Para ver correos reales de prueba se puede instalar **Mailpit** (gratis), ponerlo a escuchar en `127.0.0.1:1025` y poner `MAIL_MODO = 'smtp'` en `config.local.php` (ver abajo).

### Enviar correos reales con Google Workspace de ARPAFLU

El dominio arpaflu.com ya recibe su correo en Google Workspace, así que los avisos pueden salir por ahí, sin servidor de correo propio:

1. En Workspace, crear (o elegir) una cuenta para los avisos, por ejemplo `avisos@arpaflu.com`.
2. En esa cuenta activar la verificación en dos pasos y generar una “contraseña de aplicación”.
3. Copiar `config.local.example.php` como `config.local.php` y completar la cuenta y la contraseña de aplicación.
4. Entrar al sistema como administrador, ir a **Avisos enviados** y usar **Probar el envío de correo**.

**Importante:** `config.local.php` tiene datos privados y figura en `.gitignore`: no se sube al repositorio compartido. No escribir la contraseña en `config.php`. Los datos también se pueden pasar por variables de entorno (`ARPAFLU_MAIL_MODO`, `ARPAFLU_SMTP_HOST`, `ARPAFLU_SMTP_PORT`, `ARPAFLU_SMTP_SEGURIDAD`, `ARPAFLU_SMTP_USUARIO`, `ARPAFLU_SMTP_CLAVE`).

## Control de vencimientos (P9)

- Desde el panel: botón *Ejecutar control de vencimientos*.
- Programado en Windows (Programador de tareas) o Linux (cron): `php cron/vencimientos.php`

## Estructura

```
config.php            configuración (base de datos, correo, plazos)
instalar.php          crea la base, las tablas y los datos de ejemplo
index.php, salir.php  ingreso y salida
personal/             pantallas del personal de ARPAFLU
cliente/              portal de clientes
inc/                  lógica: base de datos, accesos, avisos, operaciones, vencimientos
partes/layout.php     estructura común de las pantallas
database/             schema.sql y seed.sql
almacen/              depósito de documentos (cerrado al acceso directo)
cron/                 proceso automático de vencimientos
css/, js/             estilos y comportamiento de la interfaz
```

## Limitaciones conocidas (es una versión de prueba)

- Las claves de las cuentas de prueba son conocidas; no usar con datos reales.
- En esta versión la clave inicial de un usuario nuevo se muestra una sola vez en pantalla (en producción iría por correo).
- “Olvidé mi clave” no cambia la clave sola: deja un aviso a los administradores, que generan una nueva desde Usuarios y permisos → Restablecer clave.
- No hay integración con portales de navieras, facturación ni contabilidad (quedan fuera del alcance, como indica el Diseño Global).
