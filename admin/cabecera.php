<?php
// cabecera.php — parte de arriba que comparten las páginas del admin.
// Antes de incluirla, cada página define $titulo (el título de la pestaña).
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo) ?> · Administración Grano Norte</title>
<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
<header class="admin-barra">
  <strong>Grano Norte · Administración</strong>
  <?php if (isset($_SESSION['admin_id'])) { ?>
  <nav>
    <a href="index.php">Cafés</a>
    <a href="../index.html" target="_blank">Ver sitio</a>
    <a href="salir.php">Cerrar sesión</a>
  </nav>
  <?php } ?>
</header>
<main class="admin-contenido">
