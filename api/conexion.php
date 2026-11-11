<?php
// =====================================================================
// conexion.php — conexión a la base de datos de Grano Norte.
// Los demás archivos PHP lo incluyen con:  require 'conexion.php';
// Los datos de conexión (usuario, clave, etc.) están en config.php.
// =====================================================================

// Abre la conexión con MariaDB/MySQL y la devuelve.
// Si algo falla (config.php no existe, clave incorrecta, etc.) lanza una
// excepción con un mensaje; quien la llame decide cómo mostrarlo.
function conectar() {
    // 1. Leer los datos de config.php
    $archivo = __DIR__ . '/config.php';
    if (!file_exists($archivo)) {
        throw new Exception('No se encontró el archivo api/config.php.');
    }
    $config = require $archivo;

    // 2. Armar la dirección de la base de datos (DSN)
    $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_puerto'] .
           ';dbname=' . $config['db_nombre'] . ';charset=utf8mb4';

    // 3. Conectar. PDO nos permite usar "consultas preparadas": los datos del
    //    usuario nunca se pegan directo en el SQL, así se evita la inyección SQL.
    try {
        $pdo = new PDO($dsn, $config['db_usuario'], $config['db_clave'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // los errores lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // los resultados vienen como arreglos con nombre
            PDO::ATTR_EMULATE_PREPARES   => false,                   // consultas preparadas reales
        ]);
    } catch (PDOException $e) {
        // El detalle técnico se guarda en el log del servidor, no se muestra.
        error_log('Grano Norte, conexión: ' . $e->getMessage());
        throw new Exception('No se pudo conectar con la base de datos. Revisa los datos de api/config.php.');
    }
    return $pdo;
}

// Devuelve el texto secreto para crear el primer administrador (admin/instalar.php).
function token_instalacion() {
    $config = require __DIR__ . '/config.php';
    return $config['setup_token'];
}
