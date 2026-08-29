<?php
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\config\db.php';
sleep(5); // Esperar un poco a que termine el background
$stmt = $pdo->query('SELECT * FROM ia_analisis_new ORDER BY creado_en DESC LIMIT 3');
print_r($stmt->fetchAll());