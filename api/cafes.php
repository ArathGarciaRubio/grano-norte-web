<?php
// =====================================================================
// cafes.php — API JSON del catálogo (la usan las páginas con fetch).
//
//   api/cafes.php              -> lista de todos los cafés activos
//   api/cafes.php?slug=nombre  -> un solo café con todos sus datos
//
// Respuesta correcta:  {"ok": true, "cafes": [...]}  o  {"ok": true, "cafe": {...}}
// Respuesta con error: {"ok": false, "error": "mensaje"}
// Los filtros del catálogo se hacen en el navegador (catalogo.js).
// =====================================================================
require 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Pide a la base una sola columna y la devuelve como lista simple.
// Ejemplo: columna($pdo, 'SELECT nombre FROM ...', [3]) -> ['Chocolate', 'Nuez']
function columna($pdo, $sql, $datos) {
    $consulta = $pdo->prepare($sql);
    $consulta->execute($datos);
    return $consulta->fetchAll(PDO::FETCH_COLUMN);
}

// Pide a la base varias filas y las devuelve como lista de arreglos.
function filas($pdo, $sql, $datos) {
    $consulta = $pdo->prepare($sql);
    $consulta->execute($datos);
    return $consulta->fetchAll();
}

try {
    $pdo = conectar();

    // 1. Datos principales de los cafés (uno o todos)
    $sql = 'SELECT c.id, c.slug, c.nombre, c.descripcion, c.proceso, c.tueste, c.altitud_msnm,
                   c.acidez, c.cuerpo, c.dulzor, c.destacado,
                   o.pais, o.region, o.subregion, o.finca, p.nombre AS productor
            FROM cafes c
            JOIN origenes o ON o.id = c.origen_id
            LEFT JOIN productores p ON p.id = c.productor_id
            WHERE c.activo = 1';
    $datos = [];
    $detalle = isset($_GET['slug']);
    if ($detalle) {
        $sql = $sql . ' AND c.slug = ?';
        $datos[] = $_GET['slug'];
    }
    $sql = $sql . ' ORDER BY c.nombre';
    $cafes = filas($pdo, $sql, $datos);

    if ($detalle && count($cafes) === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No se encontró ese café.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. A cada café se le agregan sus notas, presentaciones e imagen
    foreach ($cafes as $i => $cafe) {
        $id = $cafe['id'];
        $cafes[$i]['destacado'] = (int)$cafe['destacado'];

        $cafes[$i]['notas'] = columna($pdo,
            'SELECT n.nombre FROM cafe_notas cn JOIN notas_sensoriales n ON n.id = cn.nota_id
             WHERE cn.cafe_id = ? ORDER BY n.nombre', [$id]);

        $presentaciones = filas($pdo,
            'SELECT gramaje_g, formato, precio FROM presentaciones
             WHERE cafe_id = ? AND activo = 1 ORDER BY gramaje_g, formato', [$id]);
        foreach ($presentaciones as $j => $pres) {
            $presentaciones[$j]['precio'] = (float)$pres['precio'];   // la base lo entrega como texto
        }
        $cafes[$i]['presentaciones'] = $presentaciones;

        $imagenes = filas($pdo,
            'SELECT ruta_archivo AS ruta, texto_alternativo AS alt FROM imagenes_cafe
             WHERE cafe_id = ? ORDER BY principal DESC, id', [$id]);
        $cafes[$i]['imagenes'] = $imagenes;

        // Solo en la ficha de un café se piden variedades y métodos
        if ($detalle) {
            $cafes[$i]['variedades'] = columna($pdo,
                'SELECT v.nombre FROM cafe_variedades cv JOIN variedades v ON v.id = cv.variedad_id
                 WHERE cv.cafe_id = ? ORDER BY v.nombre', [$id]);
            $cafes[$i]['metodos'] = filas($pdo,
                'SELECT m.nombre, cm.recomendacion FROM cafe_metodos cm
                 JOIN metodos_preparacion m ON m.id = cm.metodo_id
                 WHERE cm.cafe_id = ? ORDER BY m.nombre', [$id]);
        }
    }

    // 3. Responder
    if ($detalle) {
        echo json_encode(['ok' => true, 'cafe' => $cafes[0]], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => true, 'cafes' => $cafes], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // Si algo falla se avisa con un mensaje general (el detalle queda en el log)
    error_log('Grano Norte, cafes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo consultar el catálogo.'], JSON_UNESCAPED_UNICODE);
}
