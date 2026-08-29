<?php
/**
 * Test script para verificar que la API Key de Gemini funciona
 * Ejecutar: php test_ai.php
 */

require_once __DIR__ . '/backend/services/AIService.php';

echo "=== Test Gemini API Key ===\n\n";

$aiService = new AIService();

// Test simple
$testRequest = [
    'nombre'      => 'Cliente Test',
    'email'       => 'test@example.com',
    'telefono'    => '3001234567',
    'tipo'        => 'Soporte técnico',
    'prioridad'   => 'Alta',
    'descripcion' => 'No puedo acceder a mi cuenta, me sale error 403 al intentar entrar',
];

$testHistory = [
    [
        'codigo_caso' => 'CS-2026-0001',
        'tipo'        => 'Soporte técnico',
        'estado'      => 'Resuelto',
        'fecha_creacion' => '2026-06-14 09:12:00',
        'descripcion' => 'Problema similar de acceso resuelto con reset de contraseña',
    ],
];

echo "Enviando request a Gemini...\n";
$start = microtime(true);
$result = $aiService->generateSolution($testRequest, $testHistory);
$elapsed = round(microtime(true) - $start, 2);

echo "Tiempo: {$elapsed}s\n\n";

if ($result['success']) {
    echo "✅ API Key FUNCIONA correctamente\n\n";
    echo "--- Raw Response ---\n";
    echo $result['raw'] . "\n\n";
    echo "--- Parsed Solution ---\n";
    echo json_encode($result['solution'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n";
} else {
    echo "❌ ERROR: {$result['error']}\n";
    if (isset($result['raw'])) {
        echo "Raw: {$result['raw']}\n";
    }
}

echo "\n=== Fin del test ===\n";