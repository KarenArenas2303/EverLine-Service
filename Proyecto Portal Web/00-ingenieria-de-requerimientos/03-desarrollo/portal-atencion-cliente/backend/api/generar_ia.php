<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/AIService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$codigo = trim($data['codigo'] ?? '');

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Código de caso requerido']);
    exit;
}

try {
    // Obtener solicitud y datos del cliente
    $stmt = $pdo->prepare(
        'SELECT s.id_solicitud, s.id_cliente, cat.nombre_categoria AS tipo, s.prioridad, s.descripcion,
                c.nombre, c.email, c.telefono
         FROM solicitudes s
         JOIN clientes c ON c.id_cliente = s.id_cliente
         LEFT JOIN categorias cat ON cat.id_categoria = s.id_categoria
         WHERE s.codigo_caso = ?'
    );
    $stmt->execute([$codigo]);
    $solicitud = $stmt->fetch();

    if (!$solicitud) {
        http_response_code(404);
        echo json_encode(['error' => 'Solicitud no encontrada']);
        exit;
    }

    // Obtener historial
    $stmt = $pdo->prepare(
        'SELECT s.codigo_caso, cat.nombre_categoria AS tipo, s.estado, s.descripcion, s.fecha_creacion
         FROM solicitudes s
         LEFT JOIN categorias cat ON cat.id_categoria = s.id_categoria
         WHERE s.id_cliente = ?
         ORDER BY s.fecha_creacion DESC
         LIMIT 5'
    );
    $stmt->execute([$solicitud['id_cliente']]);
    $clientHistory = $stmt->fetchAll();

    // Datos para IA
    $requestData = [
        'nombre'      => $solicitud['nombre'],
        'email'       => $solicitud['email'],
        'telefono'    => $solicitud['telefono'],
        'tipo'        => $solicitud['tipo'],
        'prioridad'   => $solicitud['prioridad'],
        'descripcion' => $solicitud['descripcion'],
    ];

    // Llamar IA
    $aiService = new AIService();
    $aiResult = $aiService->generateSolution($requestData, $clientHistory);

    $analysisData = $aiResult['success'] ? $aiResult['solution'] : ['error' => $aiResult['error']];

    // Guardar en BD
    $stmt = $pdo->prepare(
        'INSERT INTO ia_analisis_new (id_solicitud, analisis_json, creado_en) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE analisis_json = VALUES(analisis_json), creado_en = NOW()'
    );
    $stmt->execute([$solicitud['id_solicitud'], json_encode($analysisData, JSON_UNESCAPED_UNICODE)]);

    echo json_encode([
        'success' => $aiResult['success'],
        'analysis' => $analysisData,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en base de datos']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error generando análisis: ' . $e->getMessage()]);
}