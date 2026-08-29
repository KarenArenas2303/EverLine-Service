<?php
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\config\db.php';
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll());