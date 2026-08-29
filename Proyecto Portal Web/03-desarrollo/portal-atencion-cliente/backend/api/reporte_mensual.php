<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Solicitudes por mes (últimos 6 meses con datos)
    $stmtMes = $pdo->query(
        "SELECT DATE_FORMAT(fecha_creacion, '%b') AS mes,
                DATE_FORMAT(fecha_creacion, '%Y-%m') AS clave,
                COUNT(*) AS total
         FROM solicitudes
         GROUP BY clave
         ORDER BY clave ASC
         LIMIT 6"
    );
    $porMes = $stmtMes->fetchAll();

    // Distribución por tipo de solicitud
    $stmtTipo = $pdo->query(
        "SELECT cat.nombre_categoria AS tipo, COUNT(*) AS total
         FROM solicitudes s
         JOIN categorias cat ON cat.id_categoria = s.id_categoria
         GROUP BY cat.nombre_categoria
         ORDER BY total DESC"
    );
    $porTipo = $stmtTipo->fetchAll();

    // Totales generales
    $stmtTotales = $pdo->query(
        "SELECT COUNT(*) AS total,
                SUM(estado IN ('Resuelto','Cerrado')) AS resueltas
         FROM solicitudes"
    );
    $totales = $stmtTotales->fetch();

    echo json_encode([
        'por_mes'  => $porMes,
        'por_tipo' => $porTipo,
        'totales'  => $totales
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar el reporte']);
}