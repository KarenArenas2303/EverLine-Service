<?php
require_once __DIR__ . '/../config/db.php';

$email = trim($_GET['email'] ?? '');

try {
    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT s.codigo_caso, s.estado, s.fecha_creacion
             FROM solicitudes s
             JOIN clientes c ON c.id_cliente = s.id_cliente
             WHERE c.email = ?
             ORDER BY s.fecha_creacion DESC
             LIMIT 5'
        );
        $stmt->execute([$email]);
    } else {
        $stmt = $pdo->query(
            'SELECT codigo_caso, estado, fecha_creacion
             FROM solicitudes
             ORDER BY fecha_creacion DESC
             LIMIT 5'
        );
    }

    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar las solicitudes recientes']);
}