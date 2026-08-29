<?php
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\config\db.php';
$stmt = $pdo->query('DESCRIBE ia_analisis');
print_r($stmt->fetchAll());

$stmt = $pdo->query('SELECT * FROM ia_analisis ORDER BY creado_en DESC LIMIT 5');
print_r($stmt->fetchAll());