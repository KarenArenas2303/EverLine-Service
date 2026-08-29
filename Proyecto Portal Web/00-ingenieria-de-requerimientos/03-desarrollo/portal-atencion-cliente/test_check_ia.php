<?php
$ch = curl_init('http://localhost/portal-atencion-cliente/backend/api/ia_analisis.php?codigo=CS-2026-0008');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
]);
$res = curl_exec($ch);
echo $res;