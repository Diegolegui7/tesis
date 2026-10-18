<?php
// Demostración guiada paso a paso (solo para pruebas locales).
// El sistema mira la base de datos en tiempo real: cuando la persona hace lo que dice un paso, el paso se marca como hecho y aparece el siguiente.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

const DEMO_CLIENTE_ID = 6;                              // Exportadora Nueva S.R.L. (cliente nuevo, sin reservas)
const DEMO_CARLOS = 'cnuevo@exportadoranueva.com';
const DEMO_MARINA = 'msosa@arpaflu.com';

function demo_get($clave)
{
    try { return valor('SELECT valor FROM demo WHERE clave = ?', [$clave]); } catch (Exception $e) { return null; }
}

function demo_set($clave, $valor)
{
    ejecutar('INSERT INTO demo (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$clave, $valor]);
}

function demo_activa()
{
    return MODO_DEMO && demo_get('activo') === '1';
}

// Operación que nació del pedido del cliente de la demostración (la última aceptada)
function demo_operacion_id()
{
    return (int) valor("SELECT operacion_id FROM solicitudes WHERE cliente_id = ? AND estado = 'aceptada' AND operacion_id IS NOT NULL ORDER BY id DESC LIMIT 1", [DEMO_CLIENTE_ID]);
}

// Pasos de la demostración. Cada «check» recibe el momento desde el cual contar y devuelve el momento en que se cumplió (o null).
function demo_pasos()
{
    return [
        ['id' => 1, 'quien' => DEMO_CARLOS, 'titulo' => 'Entrá como cliente',
         'texto' => 'Sos Carlos, de una empresa que nunca usó el sistema. Entrá al portal con el botón «Entrar como Carlos» del panel naranja.',
         'check' => function ($t0) { return valor('SELECT 1 FROM usuarios WHERE id = 12 AND ultimo_ingreso >= ?', [$t0]) ? $t0 : null; }],
        ['id' => 2, 'ir' => ['Ir a «Pedir una reserva»', 'cliente/pedir.php'], 'quien' => DEMO_CARLOS, 'titulo' => 'Pedí una reserva',
         'texto' => 'Tocá «Pedir una reserva», elegí un barco que tenga lugar, escribí cuántos contenedores necesitás y enviá el pedido.',
         'check' => function ($t0) { return valor('SELECT MIN(creado) FROM solicitudes WHERE cliente_id = ? AND creado >= ?', [DEMO_CLIENTE_ID, $t0]); }],
        ['id' => 3, 'quien' => DEMO_MARINA, 'titulo' => 'Ahora sos ARPAFLU: entrá como Marina',
         'texto' => 'Marina es la responsable de la cuenta de Carlos. Entrá como ella con el botón «Entrar como Marina» del panel naranja.',
         'check' => function ($t0) { return valor('SELECT 1 FROM usuarios WHERE id = 2 AND ultimo_ingreso >= ?', [$t0]) ? $t0 : null; }],
        ['id' => 4, 'ir' => ['Ir a «Pedidos de clientes»', 'personal/pedidos.php'], 'quien' => DEMO_MARINA, 'titulo' => 'Respondé el pedido',
         'texto' => 'Marina recibió un aviso. Andá a «Pedidos de clientes» y aceptá el pedido de Exportadora Nueva. Se crea la reserva sola.',
         'check' => function ($t0) { return valor("SELECT MAX(respondido_en) FROM solicitudes WHERE cliente_id = ? AND estado = 'aceptada' AND respondido_en >= ?", [DEMO_CLIENTE_ID, $t0]); }],
        ['id' => 5, 'ir' => ['Ir a «Inicio»', 'cliente/reservas.php'], 'quien' => DEMO_CARLOS, 'titulo' => 'Volvé como cliente y mirá tu reserva',
         'texto' => 'Carlos recibió el aviso de que su pedido fue aceptado. Entrá como él y mirá su reserva en «Inicio».',
         'check' => function ($t0) { return valor("SELECT MAX(visto_cliente) FROM solicitudes WHERE cliente_id = ? AND estado = 'aceptada' AND visto_cliente >= ?", [DEMO_CLIENTE_ID, $t0]); }],
        ['id' => 6, 'ir' => ['Ir a la reserva', 'personal/operacion.php?id={op}'], 'quien' => DEMO_MARINA, 'titulo' => 'La naviera confirmó: cargá el booking',
         'texto' => 'En la ficha de la reserva (Operaciones), en «Siguiente paso», escribí un número de booking, por ejemplo 254AR0099001, y guardá. Se avisa al cliente.',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor('SELECT booking_confirmado_en FROM operaciones WHERE id = ? AND booking_confirmado_en >= ?', [$o, $t0]) : null; }],
        ['id' => 7, 'ir' => ['Ir a la reserva', 'personal/operacion.php?id={op}'], 'quien' => DEMO_MARINA, 'titulo' => 'Cambió el buque: registrá un roleo',
         'texto' => 'La naviera cambió el barco. En la ficha, botón «Registrar cambio»: elegí «Roleo», escribí el buque nuevo y guardá con «Guardar y avisar».',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor("SELECT MIN(creado) FROM historial_cambios WHERE operacion_id = ? AND dato LIKE 'Roleo%' AND creado >= ?", [$o, $t0]) : null; }],
        ['id' => 8, 'ir' => ['Ir a mi reserva', 'cliente/operacion.php?id={op}'], 'quien' => DEMO_CARLOS, 'titulo' => 'Confirmá que leíste el aviso',
         'texto' => 'Carlos recibió un aviso del cambio de buque. En su reserva aparece un cuadro con el botón «Notado»: tocalo.',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor("SELECT MIN(notado_en) FROM comunicaciones WHERE operacion_id = ? AND destinatario_correo = ? AND notado_en >= ?", [$o, DEMO_CARLOS, $t0]) : null; }],
        ['id' => 9, 'ir' => ['Ir a mi reserva', 'cliente/operacion.php?id={op}'], 'quien' => DEMO_CARLOS, 'titulo' => 'Enviá un documento',
         'texto' => 'En su reserva, «Enviar documento»: elegí el tipo, adjuntá cualquier archivo (un PDF o una foto) y enviá.',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor('SELECT MIN(creado) FROM documentos WHERE operacion_id = ? AND subido_por = 12 AND creado >= ?', [$o, $t0]) : null; }],
        ['id' => 10, 'ir' => ['Ir a la reserva', 'personal/operacion.php?id={op}'], 'quien' => DEMO_MARINA, 'titulo' => 'Llegó la orden de retiro: registrala',
         'texto' => 'La naviera mandó la orden para retirar el contenedor vacío. En la ficha de la reserva, en «Siguiente paso», escribí un número de orden (por ejemplo OR-5521) y tocá «Registrar orden de retiro». Recién después aparece el botón del zarpe.',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor("SELECT MIN(creado) FROM comunicaciones WHERE operacion_id = ? AND evento = 'Orden de retiro recibida' AND creado >= ?", [$o, $t0]) : null; }],
        ['id' => 11, 'ir' => ['Ir a la reserva', 'personal/operacion.php?id={op}'], 'quien' => DEMO_MARINA, 'titulo' => 'El barco salió: registrá el zarpe',
         'texto' => 'En la ficha de la reserva, en «Siguiente paso», tocá «Registrar zarpe» y confirmá. Con eso termina el recorrido de una reserva.',
         'check' => function ($t0) { $o = demo_operacion_id(); return $o ? valor('SELECT zarpe_confirmado FROM operaciones WHERE id = ? AND zarpe_confirmado >= ?', [$o, $t0]) : null; }],
        ['id' => 12, 'ir' => ['Ir a «Disponibilidad»', 'personal/disponibilidad.php'], 'quien' => DEMO_MARINA, 'titulo' => 'Cerrá el espacio disponible',
         'texto' => 'Ahora veamos qué pasa cuando no hay lugar. En «Disponibilidad», cerrá todos los embarques abiertos con el botón «Cerrar».',
         'check' => function ($t0) { require_once __DIR__ . '/solicitudes.php'; return count(disponibilidades(true)) === 0 ? valor('SELECT NOW()') : null; }],
        ['id' => 13, 'ir' => ['Ir a «Pedir una reserva»', 'cliente/pedir.php'], 'quien' => DEMO_CARLOS, 'titulo' => 'Mirá qué pasa sin lugar',
         'texto' => 'Entrá como Carlos y tocá «Pedir una reserva»: el sistema le explica que no hay lugar y no le deja llenar el formulario.',
         'check' => function ($t0) { $v = demo_get('vio_sin_lugar'); return $v && $v >= $t0 ? $v : null; }],
    ];
}

// Estado actual de la guía: cada paso con si está hecho y cuál es el que sigue
function demo_estado()
{
    $inicio = demo_get('inicio');
    if (!$inicio) { return null; }
    $omitidos = array_filter(explode(',', (string) demo_get('omitidos')));
    $pasos = demo_pasos();
    $t0 = $inicio;
    $actual = null;
    $nombres = [DEMO_CARLOS => 'Carlos (cliente)', DEMO_MARINA => 'Marina (ARPAFLU)'];
    foreach ($pasos as $i => &$p) {
        $hecho = false;
        if ($actual === null) {
            if (in_array((string) $p['id'], $omitidos, true)) {
                $hecho = true;
                $p['omitido'] = true;
            } else {
                $t = $p['check']($t0);
                if ($t) { $hecho = true; $t0 = $t; }
            }
        }
        $p['hecho'] = $hecho;
        $p['quien_nombre'] = $nombres[$p['quien']];
        if (isset($p['ir'])) {
            $op = strpos($p['ir'][1], '{op}') !== false ? demo_operacion_id() : 1;
            $p['ir'] = $op ? ['texto' => $p['ir'][0], 'ruta' => str_replace('{op}', (string) $op, $p['ir'][1])] : null;
        }
        if (!$hecho && $actual === null) { $actual = $i; }
        unset($p['check']);
    }
    unset($p);
    return ['pasos' => $pasos, 'actual' => $actual, 'total' => count($pasos), 'terminado' => $actual === null, 'inicio' => $inicio];
}

// Panel flotante con la guía (se muestra en todas las pantallas mientras la demostración esté activa)
function demo_panel()
{
    if (!MODO_DEMO) { return; }
    try { $activa = demo_activa(); } catch (Exception $e) { return; }
    if (!$activa) { return; }
    $b = base_url();
    ?>
<aside id="demo-panel" class="demo-panel" data-base="<?= e($b) ?>" data-csrf="<?= e(csrf_token()) ?>" aria-live="polite">
  <div class="demo-cab">
    <strong>Demo guiada</strong>
    <span id="demo-prog" class="demo-prog"></span>
    <button type="button" id="demo-min" class="demo-btn-icono" aria-label="Achicar o agrandar la guía" title="Achicar">–</button>
  </div>
  <div class="demo-cuerpo" id="demo-cuerpo">
    <div class="demo-barra"><div id="demo-barra-i"></div></div>
    <div id="demo-ok" class="demo-ok" hidden></div>
    <h3 id="demo-titulo">Cargando…</h3>
    <p id="demo-texto"></p>
    <div id="demo-quien" class="demo-quien"></div>
    <div class="demo-acciones">
      <button type="button" class="demo-enlace" id="demo-saltar">Saltar este paso</button>
      <button type="button" class="demo-enlace" id="demo-reiniciar">Reiniciar la demo</button>
      <button type="button" class="demo-enlace" id="demo-ocultar">Ocultar guía</button>
    </div>
  </div>
</aside>
<script src="<?= e($b) ?>js/demo.js"></script>
<?php
}
