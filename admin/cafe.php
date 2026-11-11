<?php
// =====================================================================
// cafe.php — formulario para crear o editar un café.
//   cafe.php        -> café nuevo
//   cafe.php?id=3   -> editar el café 3
// Aquí también se suben y se quitan las fotos del café.
// =====================================================================
require 'sesion.php';

$id = (int)($_GET['id'] ?? 0);
$error = '';

// ---------------------------------------------------------------------
// FUNCIONES DE AYUDA
// ---------------------------------------------------------------------

// Convierte un texto con comas en una lista: "Bourbon, Caturra" -> ['Bourbon', 'Caturra']
function texto_a_lista($texto) {
    $lista = [];
    foreach (explode(',', $texto) as $parte) {
        $parte = trim($parte);
        if ($parte !== '') {
            $lista[] = $parte;
        }
    }
    return $lista;
}

// Convierte un nombre en una dirección corta: "Chiapas Sierra Madre" -> "chiapas-sierra-madre"
function hacer_slug($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

// Busca un nombre en una tabla (variedades, notas o métodos) y devuelve su id.
// Si todavía no existe, lo crea. $tabla viene de nuestro código, nunca del usuario.
function buscar_o_crear($pdo, $tabla, $nombre) {
    $consulta = $pdo->prepare('SELECT id FROM ' . $tabla . ' WHERE nombre = ?');
    $consulta->execute([$nombre]);
    $encontrado = $consulta->fetchColumn();
    if ($encontrado) {
        return $encontrado;
    }
    $pdo->prepare('INSERT INTO ' . $tabla . ' (nombre) VALUES (?)')->execute([$nombre]);
    return $pdo->lastInsertId();
}

// ---------------------------------------------------------------------
// GUARDAR EL CAFÉ (cuando se envía el formulario principal)
// ---------------------------------------------------------------------
function guardar_cafe($pdo, $id) {
    // 1. Leer lo que escribió la persona
    $nombre = trim($_POST['nombre']);
    $pais = trim($_POST['pais']);
    $region = trim($_POST['region']);
    if ($nombre === '' || $pais === '' || $region === '') {
        throw new Exception('El nombre, el país y la región son obligatorios.');
    }
    $tueste = $_POST['tueste'] !== '' ? $_POST['tueste'] : null;
    $altitud = $_POST['altitud'] !== '' ? (int)$_POST['altitud'] : null;
    $subregion = trim($_POST['subregion']) !== '' ? trim($_POST['subregion']) : null;
    $finca = trim($_POST['finca']) !== '' ? trim($_POST['finca']) : null;

    // 2. Revisar las presentaciones (las filas vacías se ignoran)
    $presentaciones = [];
    $repetidas = [];
    for ($i = 0; $i < count($_POST['gramaje']); $i++) {
        $gramaje = (int)$_POST['gramaje'][$i];
        $formato = $_POST['formato'][$i];
        $precio = $_POST['precio'][$i];
        if ($gramaje > 0 && $precio !== '') {
            if (isset($repetidas[$gramaje . $formato])) {
                throw new Exception('Hay una presentación repetida (' . $gramaje . ' g, ' . $formato . ').');
            }
            $repetidas[$gramaje . $formato] = true;
            $presentaciones[] = [$gramaje, $formato, (float)$precio];
        }
    }

    // 3. Guardar todo junto: si algo falla, no se guarda nada (transacción)
    $pdo->beginTransaction();
    try {
        // Origen: se usa uno que ya exista o se crea
        $consulta = $pdo->prepare('SELECT id FROM origenes WHERE pais = ? AND region = ? AND IFNULL(subregion, "") = ? AND IFNULL(finca, "") = ?');
        $consulta->execute([$pais, $region, (string)$subregion, (string)$finca]);
        $origen_id = $consulta->fetchColumn();
        if (!$origen_id) {
            $pdo->prepare('INSERT INTO origenes (pais, region, subregion, finca) VALUES (?, ?, ?, ?)')->execute([$pais, $region, $subregion, $finca]);
            $origen_id = $pdo->lastInsertId();
        }

        // Productor (opcional)
        $productor_id = null;
        if (trim($_POST['productor']) !== '') {
            $productor_id = buscar_o_crear($pdo, 'productores', trim($_POST['productor']));
        }

        // Datos del café
        $datos = [$origen_id, $productor_id, $nombre, trim($_POST['descripcion']), trim($_POST['proceso']), $tueste, $altitud,
                  $_POST['acidez'], $_POST['cuerpo'], $_POST['dulzor'],
                  isset($_POST['destacado']) ? 1 : 0, isset($_POST['activo']) ? 1 : 0];
        if ($id) {
            $datos[] = $id;
            $pdo->prepare('UPDATE cafes SET origen_id = ?, productor_id = ?, nombre = ?, descripcion = ?, proceso = ?, tueste = ?,
                           altitud_msnm = ?, acidez = ?, cuerpo = ?, dulzor = ?, destacado = ?, activo = ? WHERE id = ?')->execute($datos);
        } else {
            // Café nuevo: se crea su slug y se revisa que no esté repetido
            $slug = hacer_slug($nombre);
            $numero = 2;
            $consulta = $pdo->prepare('SELECT COUNT(*) FROM cafes WHERE slug = ?');
            $consulta->execute([$slug]);
            while ($consulta->fetchColumn() > 0) {
                $slug = hacer_slug($nombre) . '-' . $numero;
                $numero = $numero + 1;
                $consulta->execute([$slug]);
            }
            $datos[] = $slug;
            $pdo->prepare('INSERT INTO cafes (origen_id, productor_id, nombre, descripcion, proceso, tueste, altitud_msnm,
                           acidez, cuerpo, dulzor, destacado, activo, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($datos);
            $id = $pdo->lastInsertId();
        }

        // Variedades y notas: se borran las anteriores y se guardan las nuevas
        $pdo->prepare('DELETE FROM cafe_variedades WHERE cafe_id = ?')->execute([$id]);
        foreach (texto_a_lista($_POST['variedades']) as $nombre_variedad) {
            $pdo->prepare('INSERT IGNORE INTO cafe_variedades (cafe_id, variedad_id) VALUES (?, ?)')
                ->execute([$id, buscar_o_crear($pdo, 'variedades', $nombre_variedad)]);
        }
        $pdo->prepare('DELETE FROM cafe_notas WHERE cafe_id = ?')->execute([$id]);
        foreach (texto_a_lista($_POST['notas']) as $nombre_nota) {
            $pdo->prepare('INSERT IGNORE INTO cafe_notas (cafe_id, nota_id) VALUES (?, ?)')
                ->execute([$id, buscar_o_crear($pdo, 'notas_sensoriales', $nombre_nota)]);
        }

        // Métodos: una línea por método, "Nombre: recomendación"
        $pdo->prepare('DELETE FROM cafe_metodos WHERE cafe_id = ?')->execute([$id]);
        foreach (explode("\n", $_POST['metodos']) as $linea) {
            $partes = explode(':', $linea, 2);
            $nombre_metodo = trim($partes[0]);
            $recomendacion = isset($partes[1]) ? trim($partes[1]) : null;
            if ($nombre_metodo !== '') {
                $pdo->prepare('INSERT IGNORE INTO cafe_metodos (cafe_id, metodo_id, recomendacion) VALUES (?, ?, ?)')
                    ->execute([$id, buscar_o_crear($pdo, 'metodos_preparacion', $nombre_metodo), $recomendacion]);
            }
        }

        // Presentaciones
        $pdo->prepare('DELETE FROM presentaciones WHERE cafe_id = ?')->execute([$id]);
        foreach ($presentaciones as $p) {
            $pdo->prepare('INSERT INTO presentaciones (cafe_id, gramaje_g, formato, precio) VALUES (?, ?, ?, ?)')
                ->execute([$id, $p[0], $p[1], $p[2]]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

// ---------------------------------------------------------------------
// SUBIR UNA FOTO
// ---------------------------------------------------------------------
function subir_foto($pdo, $id) {
    $archivo = $_FILES['foto'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Elige una imagen para subir.');
    }
    if ($archivo['size'] > 3 * 1024 * 1024) {
        throw new Exception('La imagen pesa más de 3 MB.');
    }
    // getimagesize revisa el contenido real del archivo (no solo su nombre)
    $info = getimagesize($archivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($tipos[$info[2]])) {
        throw new Exception('Solo se aceptan imágenes JPG, PNG o WebP.');
    }
    // Nombre al azar, para no usar el nombre que mandó el usuario
    $nombre_archivo = bin2hex(random_bytes(8)) . '.' . $tipos[$info[2]];
    $ruta = 'assets/images/cafes/' . $nombre_archivo;
    if (!move_uploaded_file($archivo['tmp_name'], '../' . $ruta)) {
        throw new Exception('No se pudo guardar la imagen. Revisa los permisos de la carpeta assets/images/cafes.');
    }
    // Solo puede haber una foto principal
    if (isset($_POST['principal'])) {
        $pdo->prepare('UPDATE imagenes_cafe SET principal = 0 WHERE cafe_id = ?')->execute([$id]);
    }
    $pdo->prepare('INSERT INTO imagenes_cafe (cafe_id, ruta_archivo, texto_alternativo, principal) VALUES (?, ?, ?, ?)')
        ->execute([$id, $ruta, trim($_POST['alt']), isset($_POST['principal']) ? 1 : 0]);
}

// ---------------------------------------------------------------------
// ACCIONES (cuando se envía algún formulario de esta página)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'guardar') {
            $id = guardar_cafe($pdo, $id);
            header('Location: cafe.php?id=' . $id . '&ok=1');
            exit;
        }
        if ($accion === 'subir_foto' && $id) {
            subir_foto($pdo, $id);
            header('Location: cafe.php?id=' . $id . '&ok=2');
            exit;
        }
        if ($accion === 'borrar_foto' && $id) {
            $consulta = $pdo->prepare('SELECT ruta_archivo FROM imagenes_cafe WHERE id = ? AND cafe_id = ?');
            $consulta->execute([(int)$_POST['foto_id'], $id]);
            $ruta = $consulta->fetchColumn();
            if ($ruta && strpos($ruta, 'assets/images/cafes/') === 0 && file_exists('../' . $ruta)) {
                unlink('../' . $ruta);
            }
            $pdo->prepare('DELETE FROM imagenes_cafe WHERE id = ? AND cafe_id = ?')->execute([(int)$_POST['foto_id'], $id]);
            header('Location: cafe.php?id=' . $id . '&ok=3');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Grano Norte, cafe.php: ' . $e->getMessage());
        $error = 'No se pudo guardar. Revisa los datos e inténtalo de nuevo.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ---------------------------------------------------------------------
// DATOS PARA LLENAR EL FORMULARIO
// ---------------------------------------------------------------------
// Valores de un café nuevo
$c = ['nombre' => '', 'descripcion' => '', 'proceso' => '', 'tueste' => '', 'altitud_msnm' => '', 'acidez' => 'Media',
      'cuerpo' => 'Medio', 'dulzor' => 'Media', 'destacado' => 0, 'activo' => 1,
      'pais' => '', 'region' => '', 'subregion' => '', 'finca' => '', 'productor' => '',
      'variedades' => '', 'notas' => '', 'metodos' => ''];
$presentaciones = [];
$fotos = [];

if ($error !== '') {
    // Si hubo un error al guardar, se vuelve a mostrar lo que la persona escribió
    foreach ($c as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $c[$campo] = $_POST[$campo];
        }
    }
    if (isset($_POST['gramaje'])) {
        for ($i = 0; $i < count($_POST['gramaje']); $i++) {
            $presentaciones[] = ['gramaje_g' => $_POST['gramaje'][$i], 'formato' => $_POST['formato'][$i], 'precio' => $_POST['precio'][$i]];
        }
    }
} elseif ($id) {
    // Editar: se leen los datos del café de la base de datos
    $consulta = $pdo->prepare('SELECT c.*, o.pais, o.region, o.subregion, o.finca, p.nombre AS productor
                               FROM cafes c JOIN origenes o ON o.id = c.origen_id
                               LEFT JOIN productores p ON p.id = c.productor_id WHERE c.id = ?');
    $consulta->execute([$id]);
    $c = $consulta->fetch();
    if (!$c) {
        die('No existe ese café. <a href="index.php">Volver a la lista</a>');
    }
    $consulta = $pdo->prepare('SELECT v.nombre FROM cafe_variedades cv JOIN variedades v ON v.id = cv.variedad_id WHERE cv.cafe_id = ?');
    $consulta->execute([$id]);
    $c['variedades'] = implode(', ', $consulta->fetchAll(PDO::FETCH_COLUMN));

    $consulta = $pdo->prepare('SELECT n.nombre FROM cafe_notas cn JOIN notas_sensoriales n ON n.id = cn.nota_id WHERE cn.cafe_id = ?');
    $consulta->execute([$id]);
    $c['notas'] = implode(', ', $consulta->fetchAll(PDO::FETCH_COLUMN));

    $consulta = $pdo->prepare('SELECT m.nombre, cm.recomendacion FROM cafe_metodos cm JOIN metodos_preparacion m ON m.id = cm.metodo_id WHERE cm.cafe_id = ?');
    $consulta->execute([$id]);
    $lineas = [];
    foreach ($consulta->fetchAll() as $m) {
        $lineas[] = $m['recomendacion'] ? $m['nombre'] . ': ' . $m['recomendacion'] : $m['nombre'];
    }
    $c['metodos'] = implode("\n", $lineas);

    $consulta = $pdo->prepare('SELECT gramaje_g, formato, precio FROM presentaciones WHERE cafe_id = ? ORDER BY gramaje_g, formato');
    $consulta->execute([$id]);
    $presentaciones = $consulta->fetchAll();
}
if ($id) {
    $consulta = $pdo->prepare('SELECT id, ruta_archivo, texto_alternativo FROM imagenes_cafe WHERE cafe_id = ? ORDER BY principal DESC, id');
    $consulta->execute([$id]);
    $fotos = $consulta->fetchAll();
}
// Se muestran las presentaciones que ya existen más una fila vacía (mínimo 3 filas)
while (count($presentaciones) < 3) {
    $presentaciones[] = ['gramaje_g' => '', 'formato' => 'grano', 'precio' => ''];
}
if ($id && count($presentaciones) < 4 && $presentaciones[count($presentaciones) - 1]['gramaje_g'] !== '') {
    $presentaciones[] = ['gramaje_g' => '', 'formato' => 'grano', 'precio' => ''];
}

// Función para marcar la opción elegida de un <select>
function elegida($valor, $actual) {
    return $valor === $actual ? ' selected' : '';
}

$titulo = $id ? 'Editar café' : 'Nuevo café';
require 'cabecera.php';
?>
<div class="admin-titulo">
  <h1><?= $titulo ?></h1>
  <a class="btn sec" href="index.php">Volver a la lista</a>
</div>

<?php if ($error) { ?><p class="mensaje malo"><?= h($error) ?></p><?php } ?>
<?php if (isset($_GET['ok'])) { ?><p class="mensaje">Cambios guardados.</p><?php } ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">

  <fieldset>
    <legend>Datos generales</legend>
    <div class="form-grid">
      <div class="campo"><label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?= h($c['nombre']) ?>" required></div>
      <div class="campo"><label for="proceso">Proceso</label>
        <input type="text" id="proceso" name="proceso" value="<?= h($c['proceso']) ?>" placeholder="Lavado, natural, honey..."></div>
    </div>
    <div class="campo"><label for="descripcion">Descripción</label>
      <textarea id="descripcion" name="descripcion"><?= h($c['descripcion']) ?></textarea></div>
  </fieldset>

  <fieldset>
    <legend>Origen</legend>
    <div class="form-grid">
      <div class="campo"><label for="pais">País *</label>
        <input type="text" id="pais" name="pais" value="<?= h($c['pais']) ?>" required></div>
      <div class="campo"><label for="region">Región *</label>
        <input type="text" id="region" name="region" value="<?= h($c['region']) ?>" required></div>
      <div class="campo"><label for="subregion">Subregión o zona</label>
        <input type="text" id="subregion" name="subregion" value="<?= h($c['subregion']) ?>"></div>
      <div class="campo"><label for="finca">Finca</label>
        <input type="text" id="finca" name="finca" value="<?= h($c['finca']) ?>"></div>
      <div class="campo"><label for="productor">Productor</label>
        <input type="text" id="productor" name="productor" value="<?= h($c['productor']) ?>"></div>
      <div class="campo"><label for="altitud">Altitud (msnm)</label>
        <input type="number" id="altitud" name="altitud" min="0" value="<?= h($c['altitud_msnm']) ?>"></div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Perfil de sabor</legend>
    <div class="form-grid">
      <div class="campo"><label for="tueste">Tueste</label>
        <select id="tueste" name="tueste">
          <option value="">Sin definir</option>
          <?php foreach (['claro', 'medio', 'oscuro'] as $t) { ?>
          <option value="<?= $t ?>"<?= elegida($t, (string)$c['tueste']) ?>><?= ucfirst($t) ?></option>
          <?php } ?>
        </select></div>
      <div class="campo"><label for="acidez">Acidez</label>
        <select id="acidez" name="acidez">
          <?php foreach (['Baja', 'Media', 'Alta'] as $t) { ?>
          <option<?= elegida($t, (string)$c['acidez']) ?>><?= $t ?></option>
          <?php } ?>
        </select></div>
      <div class="campo"><label for="cuerpo">Cuerpo</label>
        <select id="cuerpo" name="cuerpo">
          <?php foreach (['Ligero', 'Medio', 'Completo'] as $t) { ?>
          <option<?= elegida($t, (string)$c['cuerpo']) ?>><?= $t ?></option>
          <?php } ?>
        </select></div>
      <div class="campo"><label for="dulzor">Dulzor</label>
        <select id="dulzor" name="dulzor">
          <?php foreach (['Baja', 'Media', 'Alta'] as $t) { ?>
          <option<?= elegida($t, (string)$c['dulzor']) ?>><?= $t ?></option>
          <?php } ?>
        </select></div>
    </div>
    <div class="campo"><label for="variedades">Variedades (separadas por coma)</label>
      <input type="text" id="variedades" name="variedades" value="<?= h($c['variedades']) ?>"></div>
    <div class="campo"><label for="notas">Notas de sabor (separadas por coma)</label>
      <input type="text" id="notas" name="notas" value="<?= h($c['notas']) ?>"></div>
    <div class="campo"><label for="metodos">Métodos de preparación (uno por línea; opcional: «Método: recomendación»)</label>
      <textarea id="metodos" name="metodos"><?= h($c['metodos']) ?></textarea></div>
  </fieldset>

  <fieldset>
    <legend>Presentaciones</legend>
    <p class="meta">Deja vacía la fila que no uses. Para quitar una presentación, borra su gramaje.</p>
    <?php foreach ($presentaciones as $p) { ?>
    <div class="form-grid tres">
      <div class="campo"><label>Gramaje (g)</label>
        <input type="number" name="gramaje[]" min="1" value="<?= h($p['gramaje_g']) ?>"></div>
      <div class="campo"><label>Formato</label>
        <select name="formato[]">
          <option value="grano"<?= elegida('grano', $p['formato']) ?>>Grano</option>
          <option value="molido"<?= elegida('molido', $p['formato']) ?>>Molido</option>
        </select></div>
      <div class="campo"><label>Precio ($)</label>
        <input type="number" name="precio[]" min="0" step="0.01" value="<?= h($p['precio']) ?>"></div>
    </div>
    <?php } ?>
  </fieldset>

  <fieldset>
    <legend>Visibilidad</legend>
    <label><input type="checkbox" name="activo" <?= $c['activo'] ? 'checked' : '' ?>> Visible en el sitio</label><br>
    <label><input type="checkbox" name="destacado" <?= $c['destacado'] ? 'checked' : '' ?>> Destacado en el inicio</label>
  </fieldset>

  <button type="submit" class="btn">Guardar café</button>
</form>

<?php if ($id) { ?>
<h2>Fotos</h2>
<?php foreach ($fotos as $f) { ?>
  <form method="post" class="foto">
    <img src="../<?= h($f['ruta_archivo']) ?>" alt="<?= h($f['texto_alternativo']) ?>">
    <input type="hidden" name="accion" value="borrar_foto">
    <input type="hidden" name="foto_id" value="<?= (int)$f['id'] ?>">
    <button class="btn sec" onclick="return confirm('¿Quitar esta foto?')">Quitar</button>
  </form>
<?php } ?>
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="accion" value="subir_foto">
  <div class="campo"><label for="foto">Subir una foto (JPG, PNG o WebP, máximo 3 MB)</label>
    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" required></div>
  <div class="campo"><label for="alt">Descripción de la foto (texto alternativo)</label>
    <input type="text" id="alt" name="alt"></div>
  <label><input type="checkbox" name="principal"> Es la foto principal</label><br><br>
  <button type="submit" class="btn sec">Subir foto</button>
</form>
<?php } else { ?>
<p class="meta">Guarda el café para poder subir sus fotos.</p>
<?php } ?>
<?php require 'pie.php'; ?>
