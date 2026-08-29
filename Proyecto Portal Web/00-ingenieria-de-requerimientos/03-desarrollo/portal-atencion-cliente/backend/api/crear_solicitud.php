<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/AIService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$nombre      = trim($data['nombre'] ?? '');
$email       = trim($data['email'] ?? '');
$telefono    = trim($data['telefono'] ?? '');
$tipo        = trim($data['tipo'] ?? '');
$prioridad   = trim($data['prioridad'] ?? 'Media');
$descripcion = trim($data['descripcion'] ?? '');

if ($nombre === '' || $email === '' || $tipo === '' || $descripcion === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios']);
    exit;
}

try {
    // 1) Cliente: se busca por email, si no existe se crea
    $stmt = $pdo->prepare('SELECT id_cliente FROM clientes WHERE email = ?');
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        $idCliente = $cliente['id_cliente'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO clientes (nombre, email, telefono) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, $email, $telefono]);
        $idCliente = $pdo->lastInsertId();
    }

    // 2) Categoría según el tipo de solicitud
    $stmt = $pdo->prepare('SELECT id_categoria FROM categorias WHERE nombre_categoria = ?');
    $stmt->execute([$tipo]);
    $cat = $stmt->fetch();
    $idCategoria = $cat ? $cat['id_categoria'] : null;

    // 3) Obtener historial del cliente para la IA
    $stmt = $pdo->prepare(
        'SELECT s.codigo_caso, cat.nombre_categoria AS tipo, s.estado, s.descripcion, s.fecha_creacion
         FROM solicitudes s
         LEFT JOIN categorias cat ON cat.id_categoria = s.id_categoria
         WHERE s.id_cliente = ?
         ORDER BY s.fecha_creacion DESC
         LIMIT 5'
    );
    $stmt->execute([$idCliente]);
    $clientHistory = $stmt->fetchAll();

    // 4) Generar código de caso único: CS-AÑO-0000
    $anio = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM solicitudes WHERE codigo_caso LIKE ?");
    $stmt->execute(["CS-$anio-%"]);
    $total  = (int) $stmt->fetch()['total'] + 1;
    $codigo = sprintf('CS-%s-%04d', $anio, $total);

    // 5) Insertar la solicitud
    $stmt = $pdo->prepare(
        'INSERT INTO solicitudes (codigo_caso, id_cliente, id_categoria, prioridad, descripcion)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$codigo, $idCliente, $idCategoria, $prioridad, $descripcion]);
    $idSolicitud = $pdo->lastInsertId();

    // 6) Registrar en el historial
    $stmt = $pdo->prepare(
        'INSERT INTO historial_solicitud (id_solicitud, estado_anterior, estado_nuevo) VALUES (?, NULL, ?)'
    );
    $stmt->execute([$idSolicitud, 'Abierto']);

    // 7) Responder INMEDIATAMENTE al usuario (no bloquear por IA)
    $response = [
        'codigo_caso' => $codigo,
        'mensaje'     => 'Solicitud registrada correctamente',
        'ia_pending'  => true,
    ];

    // Enviar respuesta y cerrar conexión
    echo json_encode($response);
    
    // Permitir que el script continúe en background después de enviar respuesta
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Fallback para Apache mod_php
        ignore_user_abort(true);
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        flush();
    }

    // =========================================================
    // PROCESAMIENTO EN BACKGROUND (IA)
    // =========================================================
    $requestData = [
        'nombre'      => $nombre,
        'email'       => $email,
        'telefono'    => $telefono,
        'tipo'        => $tipo,
        'prioridad'   => $prioridad,
        'descripcion' => $descripcion,
    ];

    $aiService = new AIService();
    $aiResult = $aiService->generateSolution($requestData, $clientHistory);

    // 8) Guardar análisis de IA en BD (crear tabla si no existe)
    $analysisData = $aiResult['success'] ? $aiResult['solution'] : ['error' => $aiResult['error']];
    
    $stmt = $pdo->prepare(
        'INSERT INTO ia_analisis_new (id_solicitud, analisis_json, creado_en) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE analisis_json = VALUES(analisis_json), creado_en = NOW()'
    );
    $stmt->execute([$idSolicitud, json_encode($analysisData, JSON_UNESCAPED_UNICODE)]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la solicitud']);
}