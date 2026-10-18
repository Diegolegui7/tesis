<?php
// SS1 · Cambiar la propia contraseña (personal y clientes)
require_once __DIR__ . '/partes/layout.php';
$u = usuario_actual();
if (!$u) {
    redirigir(base_url() . 'index.php');
}
$cliente = $u['tipo'] === 'cliente';

if (es_post()) {
    csrf_verificar();
    $actual = isset($_POST['actual']) ? $_POST['actual'] : '';
    $nueva = isset($_POST['nueva']) ? $_POST['nueva'] : '';
    $repetir = isset($_POST['repetir']) ? $_POST['repetir'] : '';
    $hash = valor('SELECT clave_hash FROM usuarios WHERE id = ?', [$u['id']]);
    if (!$hash || !password_verify($actual, $hash)) {
        flash('error', 'La clave actual no es correcta.');
    } elseif (mb_strlen($nueva) < 8) {
        flash('error', 'La clave nueva tiene que tener al menos 8 caracteres.');
    } elseif ($nueva === $actual) {
        flash('error', 'La clave nueva tiene que ser distinta de la actual.');
    } elseif ($nueva !== $repetir) {
        flash('error', 'Las dos claves nuevas no coinciden.');
    } else {
        ejecutar('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        flash('ok', 'Listo, cambiaste tu clave. Usá la nueva la próxima vez que ingreses.');
        redirigir(inicio_segun_tipo($u));
    }
    redirigir('cambiar-clave.php');
}

if ($cliente) { cabecera_cliente('Mi clave', 'clave'); } else { cabecera_personal('Mi clave', 'clave'); }
?>
    <div class="encabezado-pagina">
      <div>
        <h1>Cambiar mi clave</h1>
        <p>Escribí tu clave actual y elegí una nueva de al menos 8 caracteres.</p>
      </div>
    </div>
    <form class="tarjeta" action="cambiar-clave.php" method="post" style="max-width:560px">
      <?= csrf_campo() ?>
      <div class="tarjeta-cuerpo formulario">
        <div class="campo"><label for="actual">Clave actual</label><input type="password" id="actual" name="actual" autocomplete="current-password" required></div>
        <div class="campo"><label for="nueva">Clave nueva</label><input type="password" id="nueva" name="nueva" autocomplete="new-password" minlength="8" required></div>
        <div class="campo"><label for="repetir">Repetí la clave nueva</label><input type="password" id="repetir" name="repetir" autocomplete="new-password" minlength="8" required></div>
        <div class="pie-formulario">
          <a class="boton boton-secundario" href="<?= e(inicio_segun_tipo($u)) ?>">Volver</a>
          <button type="submit" class="boton boton-primario">Cambiar clave</button>
        </div>
      </div>
    </form>
<?php if ($cliente) { pie_cliente(); } else { pie_personal(); }
