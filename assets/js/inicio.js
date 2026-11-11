// =====================================================================
// inicio.js — página de inicio: carrusel y cafés destacados.
// Las 5 imágenes del carrusel ya están escritas en index.html;
// aquí solo se decide cuál se muestra.
// =====================================================================

// ---------------------------------------------------------------------
// CARRUSEL
// ---------------------------------------------------------------------
var slides = document.querySelectorAll("#carrusel .slide");
var actual = 0;          // número de la diapositiva que se ve (empieza en 0)
var corriendo = true;    // true = avanza solo cada 5 segundos
var botonPausa = document.getElementById("car-pausa");

// Muestra la diapositiva número "numero" y oculta las demás.
function mostrarSlide(numero) {
  if (numero < 0) {
    numero = slides.length - 1;
  }
  if (numero >= slides.length) {
    numero = 0;
  }
  for (var i = 0; i < slides.length; i++) {
    slides[i].hidden = (i !== numero);
  }
  actual = numero;
}

document.getElementById("car-ant").addEventListener("click", function () {
  mostrarSlide(actual - 1);
});
document.getElementById("car-sig").addEventListener("click", function () {
  mostrarSlide(actual + 1);
});

// El botón de pausa detiene o reanuda el avance automático (accesibilidad).
botonPausa.addEventListener("click", function () {
  corriendo = !corriendo;
  if (corriendo) {
    botonPausa.textContent = "Pausar";
  } else {
    botonPausa.textContent = "Reanudar";
  }
});

// Cada 5 segundos pasa a la siguiente diapositiva, si no está en pausa.
setInterval(function () {
  if (corriendo) {
    mostrarSlide(actual + 1);
  }
}, 5000);

// ---------------------------------------------------------------------
// CAFÉS DESTACADOS
// ---------------------------------------------------------------------
var caja = document.getElementById("destacados");

pedirDatos("api/cafes.php", function (datos) {
  caja.textContent = "";
  for (var i = 0; i < datos.cafes.length; i++) {
    if (datos.cafes[i].destacado === 1) {
      caja.appendChild(crearTarjeta(datos.cafes[i]));
    }
  }
}, function (error) {
  caja.textContent = error;
});
