<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$codigo = trim($_GET['codigo'] ?? '');

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Código de caso requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT s.codigo_caso, s.estado, s.fecha_creacion, s.prioridad, s.descripcion,
                c.nombre, c.email
         FROM solicitudes s
         JOIN clientes c ON c.id_cliente = s.id_cliente
         WHERE s.codigo_caso = ?'
    );
    $stmt->execute([$codigo]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['error' => 'No se encontró una solicitud con ese código']);
        exit;
    }

    echo json_encode($ticket);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar la solicitud']);
}