<?php
$ch = curl_init('http://localhost/portal-atencion-cliente/backend/api/generar_ia.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['codigo' => 'CS-2026-0008']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 60,
]);
$res = curl_exec($ch);
echo $res;