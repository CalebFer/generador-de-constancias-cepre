<?php
require 'conexion.php';
$pdo = obtenerConexion('CEPRE_2024_1');
$stmt = $pdo->query('SELECT inicio_ciclo, fin_ciclo FROM periodos LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
