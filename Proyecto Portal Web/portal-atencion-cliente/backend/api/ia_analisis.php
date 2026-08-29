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
        'SELECT ia.analisis_json, ia.creado_en, s.estado
         FROM ia_analisis_new ia
         JOIN solicitudes s ON s.id_solicitud = ia.id_solicitud
         WHERE s.codigo_caso = ?'
    );
    $stmt->execute([$codigo]);
    $result = $stmt->fetch();

    if (!$result) {
        // Verificar si la solicitud existe
        $stmtCheck = $pdo->prepare('SELECT id_solicitud FROM solicitudes WHERE codigo_caso = ?');
        $stmtCheck->execute([$codigo]);
        $exists = $stmtCheck->fetch();
        
        if (!$exists) {
            http_response_code(404);
            echo json_encode(['error' => 'No se encontró una solicitud con ese código']);
            exit;
        }
        
        // La solicitud existe pero aún no hay análisis
        echo json_encode([
            'pending' => true,
            'message' => 'Análisis de IA en proceso...'
        ]);
        exit;
    }

    echo json_encode([
        'pending' => false,
        'analysis' => json_decode($result['analisis_json'], true),
        'generated_at' => $result['creado_en'],
        'ticket_status' => $result['estado'],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar el análisis']);
}