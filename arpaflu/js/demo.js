// Panel de la demostración guiada: consulta el estado cada 2 segundos y muestra el paso en el que está la persona
(function () {
  var panel = document.getElementById('demo-panel');
  if (!panel) { return; }
  var base = panel.dataset.base, csrf = panel.dataset.csrf;
  var $ = function (id) { return document.getElementById(id); };
  var hechosAntes = null, avisoTimer = null, ultimo = null;
  try { var g = sessionStorage.getItem('arp_demo_hechos'); if (g !== null) { hechosAntes = parseInt(g, 10); } } catch (e) {}

  function post(accion, extra) {
    var fd = new FormData();
    fd.append('csrf', csrf); fd.append('accion', accion);
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fetch(base + 'demo/accion.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function consultar() {
    fetch(base + 'demo/estado.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); }).then(pintar).catch(function () {});
  }
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  function pintar(e) {
    if (!e || !e.activo) { panel.hidden = true; return; }
    panel.hidden = false;
    ultimo = e;
    var hechos = e.pasos.filter(function (p) { return p.hecho; }).length;
    $('demo-barra-i').style.width = Math.round(100 * hechos / e.total) + '%';
    if (hechosAntes !== null && hechos > hechosAntes) {
      var ok = $('demo-ok');
      ok.textContent = '✔ ¡Paso completado: ' + e.pasos[hechos - 1].titulo + '!';
      ok.hidden = false;
      clearTimeout(avisoTimer);
      avisoTimer = setTimeout(function () { ok.hidden = true; }, 5000);
    }
    hechosAntes = hechos;
    try { sessionStorage.setItem('arp_demo_hechos', String(hechos)); } catch (e) {}

    var quien = $('demo-quien');
    if (e.terminado) {
      $('demo-prog').textContent = 'Terminada';
      $('demo-titulo').textContent = '¡Listo! Recorriste todo el circuito';
      $('demo-texto').textContent = 'Un cliente pidió una reserva, ARPAFLU la aceptó, se confirmó el lugar, hubo un cambio de buque, el cliente lo confirmó y envió un documento, el barco zarpó y se vio qué pasa cuando no hay lugar. Podés reiniciar la demo y probar otro camino.';
      quien.innerHTML = '';
      $('demo-saltar').hidden = true;
      return;
    }
    $('demo-saltar').hidden = false;
    var p = e.pasos[e.actual];
    $('demo-prog').textContent = 'Paso ' + (e.actual + 1) + ' de ' + e.total;
    $('demo-titulo').textContent = p.titulo;
    $('demo-texto').textContent = p.texto;
    if (e.usuario === p.quien) {
      quien.innerHTML = '<div class="demo-estoy">✔ Estás como <strong>' + esc(p.quien_nombre) + '</strong>. Hacelo ahora.</div>' +
        (p.ir ? '<a class="demo-boton" href="' + base + p.ir.ruta + '">' + esc(p.ir.texto) + '</a>' : '');
    } else {
      quien.innerHTML = '<div class="demo-estoy demo-cambiar">Este paso lo hace <strong>' + esc(p.quien_nombre) + '</strong>.</div>' +
        '<button type="button" class="demo-boton" data-entrar="' + esc(p.quien) + '">Entrar como ' + esc(p.quien_nombre.split(' ')[0]) + '</button>';
    }
  }

  panel.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-entrar]');
    if (b) {
      b.disabled = true;
      post('entrar', { correo: b.dataset.entrar }).then(function (j) { if (j.ok) { location.href = j.redirect; } else { alert(j.error || 'No se pudo entrar.'); b.disabled = false; } });
    }
  });
  $('demo-saltar').addEventListener('click', function () { post('saltar').then(consultar); });
  $('demo-ocultar').addEventListener('click', function () { post('ocultar').then(function () { location.reload(); }); });
  $('demo-reiniciar').addEventListener('click', function () {
    if (!confirm('¿Reiniciar la demo? Se borran los datos que cargaste y se vuelve a empezar desde el paso 1.')) { return; }
    post('reiniciar').then(function (j) { if (j.ok) { location.href = j.redirect; } else { alert(j.error || 'No se pudo reiniciar.'); } });
  });
  $('demo-min').addEventListener('click', function () {
    var mini = panel.classList.toggle('demo-mini');
    $('demo-min').textContent = mini ? '+' : '–';
    try { localStorage.setItem('arp_demo_mini', mini ? '1' : '0'); } catch (e) {}
  });
  try { if (localStorage.getItem('arp_demo_mini') === '1') { panel.classList.add('demo-mini'); $('demo-min').textContent = '+'; } } catch (e) {}

  consultar();
  setInterval(consultar, 2000);
})();
