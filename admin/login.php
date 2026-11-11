<?php
// =====================================================================
// login.php — inicio de sesión del administrador.
// Compara la contraseña escrita con el "hash" guardado en la base de datos.
// (Las contraseñas nunca se guardan en texto normal.)
// =====================================================================
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require __DIR__ . '/../api/conexion.php';

$error = '';

// Si ya inició sesión, va directo a la lista de cafés.
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';

    try {
        $pdo = conectar();
        $consulta = $pdo->prepare('SELECT id, password_hash FROM administradores WHERE usuario = ? AND activo = 1');
        $consulta->execute([$usuario]);
        $admin = $consulta->fetch();

        if ($admin && password_verify($clave, $admin['password_hash'])) {
            session_regenerate_id(true);          // nueva sesión al entrar (más seguro)
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_usuario'] = $usuario;
            header('Location: index.php');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';   // mismo mensaje para los dos casos
    } catch (Exception $e) {
        $error = 'No se pudo conectar con la base de datos.';
    }
}

$titulo = 'Iniciar sesión';
require 'cabecera.php';
?>
<section class="admin-caja">
  <h1>Iniciar sesión</h1>
  <?php if ($error) { ?><p class="mensaje malo"><?= htmlspecialchars($error) ?></p><?php } ?>
  <form method="post">
    <div class="campo"><label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" required autofocus></div>
    <div class="campo"><label for="clave">Contraseña</label>
      <input type="password" id="clave" name="clave" required></div>
    <button type="submit" class="btn">Entrar</button>
  </form>
</section>
<?php require 'pie.php'; ?>
