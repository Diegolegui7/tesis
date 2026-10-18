// ARPAFLU · comportamiento de la interfaz
// Solo maneja la interfaz; los datos y las reglas del negocio los resuelve PHP en el servidor.
 
document.addEventListener('DOMContentLoaded', function () {
 
  // Menú lateral en pantallas chicas
  var botonMenu = document.querySelector('.boton-menu');
  var velo = document.querySelector('.velo');
  if (botonMenu) {
    botonMenu.addEventListener('click', function () {
      document.body.classList.toggle('menu-abierto');
    });
  }
  if (velo) {
    velo.addEventListener('click', function () {
      document.body.classList.remove('menu-abierto');
    });
  }
 
  // Pestañas: <button class="pestana" data-pestana="id"> abre <section class="panel-pestana" id="id">
  document.querySelectorAll('.pestanas').forEach(function (grupo) {
    var botones = grupo.querySelectorAll('.pestana');
    botones.forEach(function (boton) {
      boton.addEventListener('click', function () {
        botones.forEach(function (b) {
          b.classList.remove('activa');
          b.setAttribute('aria-selected', 'false');
          var panel = document.getElementById(b.dataset.pestana);
          if (panel) panel.hidden = true;
        });
        boton.classList.add('activa');
        boton.setAttribute('aria-selected', 'true');
        var panel = document.getElementById(boton.dataset.pestana);
        if (panel) panel.hidden = false;
        history.replaceState(null, '', '#' + boton.dataset.pestana);
      });
    });
 
    // Abrir la pestaña indicada en la dirección (por ejemplo operacion.html#cambios)
    var inicial = location.hash.slice(1);
    var botonInicial = inicial && grupo.querySelector('.pestana[data-pestana="' + inicial + '"]');
    if (botonInicial) botonInicial.click();
  });
 
  // Botones que abren una pestaña de la misma página: <a data-ir="id">
  document.querySelectorAll('[data-ir]').forEach(function (enlace) {
    enlace.addEventListener('click', function (e) {
      var boton = document.querySelector('.pestana[data-pestana="' + enlace.dataset.ir + '"]');
      if (!boton) return;
      e.preventDefault();
      boton.click();
      boton.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
 
  // Selección de alternativas (portal del cliente)
  var alternativas = document.querySelectorAll('.alternativa');
  var botonConfirmar = document.getElementById('confirmar-seleccion');
  alternativas.forEach(function (alt) {
    var radio = alt.querySelector('input[type="radio"]');
    radio.addEventListener('change', function () {
      alternativas.forEach(function (a) { a.classList.remove('elegida'); });
      alt.classList.add('elegida');
      if (botonConfirmar) botonConfirmar.disabled = false;
    });
  });
 
  // Mostrar u ocultar formularios desplegables: <button data-alternar="id">
  document.querySelectorAll('[data-alternar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var destino = document.getElementById(boton.dataset.alternar);
      if (!destino) return;
      destino.hidden = !destino.hidden;
      if (!destino.hidden) {
        var primerCampo = destino.querySelector('input, select, textarea');
        if (primerCampo) primerCampo.focus();
      }
    });
  });
 
  // Filtro rápido de tablas: <input data-filtrar="id-de-tabla">
  document.querySelectorAll('[data-filtrar]').forEach(function (campo) {
    var tabla = document.getElementById(campo.dataset.filtrar);
    if (!tabla) return;
    campo.addEventListener('input', function () {
      var texto = campo.value.trim().toLowerCase();
      tabla.querySelectorAll('tbody tr').forEach(function (fila) {
        fila.hidden = texto !== '' && fila.textContent.toLowerCase().indexOf(texto) === -1;
      });
    });
  });
});

// Portal de clientes: confirmaciones y recorrido de bienvenida
document.addEventListener('DOMContentLoaded', function () {

  // Formularios con <form data-confirmar="mensaje">: piden confirmación antes de enviar
  document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
  });

  // Recorrido de bienvenida (se muestra la primera vez que entra desde este navegador)
  var tour = document.getElementById('tour');
  if (tour) {
    var pasos = tour.querySelectorAll('.tour-paso');
    var puntos = tour.querySelector('.tour-puntos');
    var siguiente = tour.querySelector('[data-tour-siguiente]');
    var actual = 0;
    pasos.forEach(function () { puntos.appendChild(document.createElement('i')); });
    var mostrar = function (n) {
      actual = n;
      pasos.forEach(function (p, i) { p.hidden = i !== n; });
      puntos.querySelectorAll('i').forEach(function (d, i) { d.classList.toggle('on', i === n); });
      siguiente.textContent = n === pasos.length - 1 ? 'Empezar' : 'Siguiente';
    };
    var cerrar = function () {
      tour.hidden = true;
      try { localStorage.setItem('arp_tour_visto', '1'); } catch (e) {}
    };
    var abrir = function () { mostrar(0); tour.hidden = false; siguiente.focus(); };
    siguiente.addEventListener('click', function () { if (actual >= pasos.length - 1) cerrar(); else mostrar(actual + 1); });
    tour.querySelector('[data-tour-cerrar]').addEventListener('click', cerrar);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !tour.hidden) cerrar(); });
    document.querySelectorAll('[data-tour-abrir]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); abrir(); }); });
    var visto = false;
    try { visto = localStorage.getItem('arp_tour_visto') === '1'; } catch (e) {}
    if (!visto && document.getElementById('demo-panel') === null && /\/cliente\/reservas\.php/.test(location.pathname)) abrir();
  }
});
