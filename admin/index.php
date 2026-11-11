<?php
// =====================================================================
// index.php — lista de cafés del panel de administración.
// Desde aquí se puede: crear, editar, activar/desactivar, destacar y borrar.
// =====================================================================
require 'sesion.php';

// ---- Acciones: los botones de cada fila envían un formulario POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'activo' || $accion === 'destacado') {
        // 1 - campo: si estaba en 1 pasa a 0, y si estaba en 0 pasa a 1
        $pdo->prepare('UPDATE cafes SET ' . $accion . ' = 1 - ' . $accion . ' WHERE id = ?')->execute([$id]);
    }

    if ($accion === 'borrar') {
        // Primero se borran los archivos de las fotos y luego el café
        // (sus presentaciones, notas e imágenes se borran solas por la base de datos).
        $consulta = $pdo->prepare('SELECT ruta_archivo FROM imagenes_cafe WHERE cafe_id = ?');
        $consulta->execute([$id]);
        foreach ($consulta->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
            if (strpos($ruta, 'assets/images/cafes/') === 0 && file_exists('../' . $ruta)) {
                unlink('../' . $ruta);
            }
        }
        $pdo->prepare('DELETE FROM cafes WHERE id = ?')->execute([$id]);
    }
    header('Location: index.php');
    exit;
}

// ---- Lista de cafés ----
$cafes = $pdo->query(
    'SELECT c.id, c.nombre, c.tueste, c.activo, c.destacado, o.pais, o.region,
            (SELECT MIN(precio) FROM presentaciones WHERE cafe_id = c.id) AS desde
     FROM cafes c JOIN origenes o ON o.id = c.origen_id
     ORDER BY c.nombre')->fetchAll();

$titulo = 'Cafés';
require 'cabecera.php';
?>
<div class="admin-titulo">
  <h1>Cafés (<?= count($cafes) ?>)</h1>
  <a class="btn" href="cafe.php">Nuevo café</a>
</div>

<table class="tabla">
  <thead>
    <tr><th>Nombre</th><th>Origen</th><th>Tueste</th><th>Desde</th><th>Estado</th><th>Acciones</th></tr>
  </thead>
  <tbody>
  <?php foreach ($cafes as $c) { ?>
    <tr>
      <td><?= h($c['nombre']) ?></td>
      <td><?= h($c['pais'] . ', ' . $c['region']) ?></td>
      <td><?= h($c['tueste']) ?></td>
      <td><?= $c['desde'] !== null ? '$' . h($c['desde']) : '—' ?></td>
      <td><?= $c['activo'] ? 'Visible' : 'Oculto' ?><?= $c['destacado'] ? ' · Destacado' : '' ?></td>
      <td>
        <a class="btn sec" href="cafe.php?id=<?= (int)$c['id'] ?>">Editar</a>
        <form method="post" class="en-linea">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn sec" name="accion" value="activo"><?= $c['activo'] ? 'Ocultar' : 'Mostrar' ?></button>
          <button class="btn sec" name="accion" value="destacado"><?= $c['destacado'] ? 'Quitar destacado' : 'Destacar' ?></button>
          <button class="btn sec" name="accion" value="borrar" onclick="return confirm('¿Borrar este café? No se puede deshacer.')">Borrar</button>
        </form>
      </td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php require 'pie.php'; ?>
