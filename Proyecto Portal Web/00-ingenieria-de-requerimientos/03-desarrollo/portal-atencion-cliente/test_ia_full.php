<?php
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\services\AIService.php';
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\config\db.php';

$requestData = [
    'nombre'      => 'Test',
    'email'       => 'test@test.com',
    'telefono'    => '3001234567',
    'tipo'        => 'Soporte técnico',
    'prioridad'   => 'Alta',
    'descripcion' => 'No puedo acceder a mi cuenta, error 403',
];

$clientHistory = [];

$aiService = new AIService();
echo "Llamando a IA...\n";
$start = microtime(true);
$result = $aiService->generateSolution($requestData, $clientHistory);
$elapsed = round(microtime(true) - $start, 2);

echo "Tiempo: {$elapsed}s\n";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";

if ($result['success']) {
    print_r($result['solution']);
} else {
    echo "Error: " . $result['error'] . "\n";
}

// Guardar en BD
if ($result['success']) {
    $analysisData = $result['solution'];
} else {
    $analysisData = ['error' => $result['error']];
}

$stmt = $pdo->prepare(
    'INSERT INTO ia_analisis_new (id_solicitud, analisis_json, creado_en) VALUES (?, ?, NOW())'
);
$stmt->execute([8, json_encode($analysisData, JSON_UNESCAPED_UNICODE)]);
echo "Guardado en BD\n";