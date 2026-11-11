// =====================================================================
// catalogo.js — catálogo con filtros.
// Se piden todos los cafés una sola vez y los filtros se aplican aquí,
// en el navegador, cada vez que la persona cambia un campo.
// =====================================================================
var todos = [];   // todos los cafés que mandó la API
var formulario = document.getElementById("filtros");
var lista = document.getElementById("lista");
var info = document.getElementById("resultado-info");

// Revisa si un café cumple con los filtros que escribió la persona.
//   cafe = un café de la lista
// Devuelve true si se debe mostrar y false si no.
function cumpleFiltros(cafe) {
  // Búsqueda de texto: nombre, país, región y notas de sabor
  var texto = limpiar(formulario.q.value.trim());
  if (texto !== "") {
    var donde = limpiar(cafe.nombre + " " + cafe.pais + " " + cafe.region + " " + cafe.notas.join(" "));
    if (donde.indexOf(texto) === -1) {
      return false;
    }
  }

  // País y tueste
  if (formulario.pais.value !== "" && formulario.pais.value !== cafe.pais) {
    return false;
  }
  if (formulario.tueste.value !== "" && formulario.tueste.value !== cafe.tueste) {
    return false;
  }

  // Presentación (formato, gramaje y precio): debe haber UNA presentación
  // que cumpla las tres cosas a la vez.
  var formato = formulario.formato.value;
  var gramaje = formulario.gramaje.value;
  var precioMaximo = formulario.precio.value;
  if (formato === "" && gramaje === "" && precioMaximo === "") {
    return true;
  }
  for (var i = 0; i < cafe.presentaciones.length; i++) {
    var pres = cafe.presentaciones[i];
    var sirve = true;
    if (formato !== "" && pres.formato !== formato) {
      sirve = false;
    }
    if (gramaje !== "" && pres.gramaje_g !== Number(gramaje)) {
      sirve = false;
    }
    if (precioMaximo !== "" && pres.precio > Number(precioMaximo)) {
      sirve = false;
    }
    if (sirve) {
      return true;
    }
  }
  return false;
}

// Dibuja en pantalla los cafés que cumplen los filtros.
function mostrar() {
  lista.textContent = "";
  var total = 0;
  for (var i = 0; i < todos.length; i++) {
    if (cumpleFiltros(todos[i])) {
      lista.appendChild(crearTarjeta(todos[i]));
      total = total + 1;
    }
  }
  if (total === 0) {
    info.textContent = "No hay cafés con esos filtros. Prueba quitando alguno.";
  } else if (total === 1) {
    info.textContent = "1 café encontrado.";
  } else {
    info.textContent = total + " cafés encontrados.";
  }
}

// Si la página se abrió desde "Encuentra tu café", los filtros vienen en la
// dirección (catalogo.html?q=Chocolate&tueste=medio). Se copian al formulario.
function leerFiltrosDeLaDireccion() {
  var parametros = new URLSearchParams(location.search);
  var campos = ["q", "pais", "tueste", "formato", "gramaje", "precio"];
  for (var i = 0; i < campos.length; i++) {
    var valor = parametros.get(campos[i]);
    if (valor) {
      formulario[campos[i]].value = valor;
    }
  }
}

// Cada vez que se cambia un campo se vuelve a filtrar.
formulario.addEventListener("input", mostrar);
// "Quitar filtros": el navegador vacía el formulario y luego se vuelve a mostrar todo.
formulario.addEventListener("reset", function () {
  setTimeout(mostrar, 0);
});

// Inicio: pedir los cafés a la API.
pedirDatos("api/cafes.php", function (datos) {
  todos = datos.cafes;
  leerFiltrosDeLaDireccion();
  mostrar();
}, function (error) {
  info.textContent = error;
});
