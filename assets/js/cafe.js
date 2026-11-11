// =====================================================================
// cafe.js — ficha de un café (cafe.html?slug=nombre-del-cafe).
// Pide a la API los datos de ese café y los dibuja en la página.
// =====================================================================
var detalle = document.getElementById("detalle");

// Agrega un título y una lista de textos a la ficha.
//   titulo  = texto del título (h2)
//   lista   = arreglo de textos
//   destino = elemento donde se agrega
function agregarLista(destino, titulo, lista) {
  if (lista.length === 0) {
    return;
  }
  destino.appendChild(crear("h2", "", titulo));
  var ul = crear("ul");
  for (var i = 0; i < lista.length; i++) {
    ul.appendChild(crear("li", "", lista[i]));
  }
  destino.appendChild(ul);
}

// Arma el texto de una presentación. Ejemplo: "Grano · 250 g · $185"
function textoPresentacion(pres) {
  var formato = pres.formato.charAt(0).toUpperCase() + pres.formato.slice(1);
  var gramos = pres.gramaje_g + " g";
  if (pres.gramaje_g === 1000) {
    gramos = "1 kg";
  }
  return formato + " · " + gramos + " · " + dinero(pres.precio);
}

// Dibuja toda la ficha del café.
function mostrarCafe(cafe) {
  document.title = cafe.nombre + " · Grano Norte";
  document.getElementById("migas-nombre").textContent = cafe.nombre;
  detalle.textContent = "";

  var caja = crear("div", "detalle");

  // Columna 1: imagen
  var imagen = crear("div", "ph r43");
  imagen.appendChild(crear("span", "", "Fotografía por agregar"));
  if (cafe.imagenes.length > 0) {
    var foto = document.createElement("img");
    foto.src = cafe.imagenes[0].ruta;
    foto.alt = cafe.imagenes[0].alt || cafe.nombre;
    imagen.appendChild(foto);
  }
  caja.appendChild(imagen);

  // Columna 2: datos
  var datos = crear("div");
  datos.appendChild(crear("h1", "", cafe.nombre));
  datos.appendChild(crear("p", "meta", cafe.pais + " · " + cafe.region));
  datos.appendChild(crear("p", "", cafe.descripcion));

  var notas = crear("ul", "chips");
  for (var i = 0; i < cafe.notas.length; i++) {
    notas.appendChild(crear("li", "chip", cafe.notas[i]));
  }
  datos.appendChild(notas);

  // Presentaciones: se elige una y se ve su precio
  datos.appendChild(crear("h2", "", "Presentaciones"));
  var etiqueta = crear("label", "", "Elige una presentación");
  etiqueta.htmlFor = "presentacion";
  datos.appendChild(etiqueta);
  var selector = crear("select");
  selector.id = "presentacion";
  for (var j = 0; j < cafe.presentaciones.length; j++) {
    var opcion = crear("option", "", textoPresentacion(cafe.presentaciones[j]));
    opcion.value = cafe.presentaciones[j].precio;
    selector.appendChild(opcion);
  }
  datos.appendChild(selector);
  var precio = crear("p", "precio-grande");
  datos.appendChild(precio);

  // El precio cambia cuando se elige otra presentación
  function actualizarPrecio() {
    if (cafe.presentaciones.length === 0) {
      precio.textContent = "Sin presentaciones disponibles";
    } else {
      precio.textContent = dinero(selector.value);
    }
  }
  selector.addEventListener("change", actualizarPrecio);
  actualizarPrecio();

  datos.appendChild(crear("p", "meta", "Este sitio no vende en línea: para pedir este café usa la página de contacto."));
  var boton = crear("a", "btn", "Preguntar por este café");
  boton.href = "contacto.html";
  datos.appendChild(boton);
  caja.appendChild(datos);
  detalle.appendChild(caja);

  // Ficha técnica
  var ficha = [];
  ficha.push("Proceso: " + cafe.proceso);
  ficha.push("Tueste: " + cafe.tueste);
  if (cafe.altitud_msnm) {
    ficha.push("Altitud: " + cafe.altitud_msnm + " msnm");
  }
  if (cafe.subregion) {
    ficha.push("Zona: " + cafe.subregion);
  }
  if (cafe.productor) {
    ficha.push("Productor: " + cafe.productor);
  }
  ficha.push("Acidez: " + cafe.acidez + " · Cuerpo: " + cafe.cuerpo + " · Dulzor: " + cafe.dulzor);
  agregarLista(detalle, "Ficha del café", ficha);
  agregarLista(detalle, "Variedades", cafe.variedades);

  // Métodos de preparación
  var metodos = [];
  for (var k = 0; k < cafe.metodos.length; k++) {
    var texto = cafe.metodos[k].nombre;
    if (cafe.metodos[k].recomendacion) {
      texto = texto + ": " + cafe.metodos[k].recomendacion;
    }
    metodos.push(texto);
  }
  agregarLista(detalle, "Cómo prepararlo", metodos);
}

// Inicio: leer el slug de la dirección y pedir el café.
var slug = new URLSearchParams(location.search).get("slug");
if (!slug) {
  detalle.textContent = "No se indicó qué café mostrar. Vuelve al catálogo.";
} else {
  pedirDatos("api/cafes.php?slug=" + encodeURIComponent(slug), function (datos) {
    mostrarCafe(datos.cafe);
  }, function (error) {
    detalle.textContent = error;
  });
}
