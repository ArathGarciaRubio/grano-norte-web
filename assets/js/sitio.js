// =====================================================================
// sitio.js — funciones que usan varias páginas del sitio.
// JavaScript simple, sin bibliotecas.
// =====================================================================

// ---------------------------------------------------------------------
// MENÚ EN CELULAR: el botón "Menú" muestra u oculta la lista de enlaces
// ---------------------------------------------------------------------
var botonMenu = document.getElementById("menu-boton");
var menu = document.getElementById("menu");

botonMenu.addEventListener("click", function () {
  menu.classList.toggle("abierto");
  botonMenu.setAttribute("aria-expanded", menu.classList.contains("abierto"));
});

// ---------------------------------------------------------------------
// FUNCIONES DE AYUDA
// ---------------------------------------------------------------------

// Crea un elemento HTML con una clase y un texto.
// Ejemplo: crear("p", "meta", "Hola")  ->  <p class="meta">Hola</p>
// Usa textContent (no innerHTML) para que un texto nunca se lea como código.
function crear(etiqueta, clase, texto) {
  var elemento = document.createElement(etiqueta);
  if (clase) {
    elemento.className = clase;
  }
  if (texto) {
    elemento.textContent = texto;
  }
  return elemento;
}

// Convierte un número en texto de dinero. Ejemplo: 185 -> "$185"
function dinero(numero) {
  return "$" + numero;
}

// Devuelve el precio más bajo de un café.
function precioDesde(cafe) {
  var menor = null;
  for (var i = 0; i < cafe.presentaciones.length; i++) {
    var precio = cafe.presentaciones[i].precio;
    if (menor === null || precio < menor) {
      menor = precio;
    }
  }
  return menor;
}

// Quita acentos y pasa a minúsculas, para buscar "limon" y encontrar "Limón".
function limpiar(texto) {
  return texto.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
}

// ---------------------------------------------------------------------
// DATOS DEL CATÁLOGO (se piden a api/cafes.php)
// ---------------------------------------------------------------------

// Pide a la API un café o toda la lista, y llama a "alTerminar" con la respuesta.
//   direccion  = "api/cafes.php" o "api/cafes.php?slug=..."
//   alTerminar = función que recibe los datos
//   alFallar   = función que recibe un texto de error
function pedirDatos(direccion, alTerminar, alFallar) {
  fetch(direccion)
    .then(function (respuesta) {
      return respuesta.json();
    })
    .then(function (datos) {
      if (datos.ok) {
        alTerminar(datos);
      } else {
        alFallar(datos.error);
      }
    })
    .catch(function () {
      alFallar("No se pudo conectar con el servidor.");
    });
}

// ---------------------------------------------------------------------
// TARJETA DE CAFÉ (se usa en el inicio y en el catálogo)
// ---------------------------------------------------------------------

// Arma la tarjeta de un café y la devuelve lista para agregarla a la página.
function crearTarjeta(cafe) {
  var tarjeta = crear("article", "tarjeta");

  // Imagen: si el café no tiene foto, se muestra un recuadro.
  var imagen = crear("div", "ph r43");
  imagen.appendChild(crear("span", "", "Fotografía por agregar"));
  if (cafe.imagenes.length > 0) {
    var foto = document.createElement("img");
    foto.src = cafe.imagenes[0].ruta;
    foto.alt = cafe.imagenes[0].alt || cafe.nombre;
    imagen.appendChild(foto);
  }
  tarjeta.appendChild(imagen);

  // Texto
  var cuerpo = crear("div", "cuerpo");
  var titulo = crear("h3");
  var enlace = crear("a", "", cafe.nombre);
  enlace.href = "cafe.html?slug=" + cafe.slug;
  titulo.appendChild(enlace);
  cuerpo.appendChild(titulo);
  cuerpo.appendChild(crear("p", "meta", cafe.pais + " · " + cafe.region));

  var notas = crear("ul", "chips");
  for (var i = 0; i < cafe.notas.length && i < 3; i++) {
    notas.appendChild(crear("li", "chip", cafe.notas[i]));
  }
  cuerpo.appendChild(notas);

  cuerpo.appendChild(crear("p", "meta", "Tueste " + cafe.tueste + " · " + cafe.proceso));
  var precio = precioDesde(cafe);
  if (precio !== null) {
    cuerpo.appendChild(crear("p", "precio", "Desde " + dinero(precio)));
  }
  tarjeta.appendChild(cuerpo);
  return tarjeta;
}
