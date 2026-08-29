<?php
$ch = curl_init('http://localhost/portal-atencion-cliente/backend/api/crear_solicitud.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'nombre' => 'Test',
        'email' => 'test@test.com',
        'tipo' => 'Soporte técnico',
        'prioridad' => 'Media',
        'descripcion' => 'Prueba de error'
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$res = curl_exec($ch);
echo $res;