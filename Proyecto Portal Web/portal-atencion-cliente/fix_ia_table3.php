<?php
require_once 'C:\xampp\htdocs\portal-atencion-cliente\backend\config\db.php';
try {
    // Discard tablespace first
    $pdo->exec('ALTER TABLE ia_analisis DISCARD TABLESPACE');
    echo "Tablespace descartado\n";
} catch (PDOException $e) {
    echo "Error discard: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('DROP TABLE IF EXISTS ia_analisis');
    echo "Tabla eliminada\n";
} catch (PDOException $e) {
    echo "Error drop: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("
        CREATE TABLE ia_analisis (
          id_analisis     INT AUTO_INCREMENT PRIMARY KEY,
          id_solicitud    INT NOT NULL UNIQUE,
          analisis_json   JSON NOT NULL,
          creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_ia_solicitud
            FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud)
        ) ENGINE=InnoDB
    ");
    echo "Tabla creada exitosamente\n";
} catch (PDOException $e) {
    echo "Error create: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query('DESCRIBE ia_analisis');
    print_r($stmt->fetchAll());
} catch (PDOException $e) {
    echo "Error describe: " . $e->getMessage() . "\n";
}