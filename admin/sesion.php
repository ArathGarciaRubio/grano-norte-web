<?php
// =====================================================================
// sesion.php — protege las páginas de administración.
// Cada página del admin (menos login.php) empieza con:  require 'sesion.php';
// Si la persona no inició sesión, la manda a login.php.
// Deja listas dos cosas: la conexión $pdo y la función h().
// =====================================================================

// La cookie de sesión no se puede leer con JavaScript y no viaja entre sitios.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/../api/conexion.php';
try {
    $pdo = conectar();
} catch (Exception $e) {
    die('No se pudo conectar con la base de datos.');
}

// Escapa un texto antes de mostrarlo en HTML (evita que se interprete como código).
// Uso: poner h($nombre) dentro de un echo.
function h($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}
