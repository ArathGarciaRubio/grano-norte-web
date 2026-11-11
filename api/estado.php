<?php
// estado.php — prueba rápida de la conexión.
// Se abre desde el navegador: api/estado.php
// Si todo está bien responde {"ok":true,...}. Si no, explica qué falta.
require 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = conectar();
    $total = $pdo->query('SELECT COUNT(*) FROM cafes WHERE activo = 1')->fetchColumn();
    echo json_encode(['ok' => true, 'base_de_datos' => 'conectada', 'cafes_activos' => (int)$total, 'php' => PHP_VERSION]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
