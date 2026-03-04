<?php
// conexion.php

/**
 * Devuelve el arreglo con todas las bases de datos configuradas para cada ciclo.
 * 
 * @return array Arreglo asociativo de Ciclo => NombreBD
 */
function obtenerBasesDeDatos() {
    return [
        'CEPRE_2022_1' => 'cepre',
        'CEPRE_2022_2'  => 'cepre_2022_2023',
        'CEPRE_2023_1' => 'cepre_2023_1',
        'CEPRE_2023_2' => 'cepre_2023_2',
        'CEPRE_2024_1' => 'cepre_2024_1',
        'CEPRE_2024_2' => 'cepre_2024_2',
        'CEPRE_2025_1' => 'marzo_julio_2025',
    ];
}

/**
 * Devuelve los nombres de los ciclos (claves) que están configurados.
 *
 * @return array
 */
function obtenerCiclosDisponibles() {
    return array_keys(obtenerBasesDeDatos());
}

/**
 * Función para obtener la conexión a la base de datos dependiendo del ciclo
 * 
 * @param string $ciclo El nombre del ciclo académico
 * @return PDO La instancia de conexión PDO
 * @throws Exception Si no hay configuración para el ciclo o falla la conexión
 */
function obtenerConexion($ciclo) {
    // Configuración general del servidor de base de datos
    $host = '127.0.0.1';
    $usuario = 'admincepre'; // Cambiar por tu usuario real
    $password = 'cepre2026';    // Cambiar por tu contraseña real
    
    // Obtenemos el listado global de ciclos
    $bases_de_datos = obtenerBasesDeDatos();

    // Verificar si el ciclo solicitado tiene una base de datos asignada
    if (!isset($bases_de_datos[$ciclo])) {
        throw new Exception("No existe una base de datos configurada para el ciclo: " . $ciclo);
    }

    $db_nombre = $bases_de_datos[$ciclo];

    try {
        // Cadena de conexión (DSN)
        $dsn = "mysql:host=$host;dbname=$db_nombre;charset=utf8mb4";
        
        // Opciones de PDO para mejor manejo de errores y caracteres
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos por defecto
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación para mayor seguridad
        ];
        
        // Crear y retornar la conexión
        $pdo = new PDO($dsn, $usuario, $password, $opciones);
        return $pdo;

    } catch (PDOException $e) {
        // Capturar error de conexión y lanzar una excepción manejable
        throw new Exception("Error de conexión a la base de datos '$db_nombre': " . $e->getMessage());
    }
}

function existeColumna(PDO $pdo, $tabla, $columna) {
    // Sanitizar un poco si es necesario, aunque son valores controlados
    $sql = "SHOW COLUMNS FROM `$tabla` LIKE '$columna'";
    $stmt = $pdo->query($sql);
    return $stmt->rowCount() > 0;
}

function obtenerCaseSuplente(PDO $pdo) {

    $tieneTipo = existeColumna($pdo, 'carga_academicas', 'tipo');
    $tieneObservacion = existeColumna($pdo, 'asistencia_docentes', 'observacion');

    if ($tieneTipo) {
        return "
            CASE 
                WHEN ca.tipo = '2' THEN 'Suplente'
                ELSE 'Principal'
            END
        ";
    }

    if ($tieneObservacion) {
        return "
            CASE 
                WHEN a.observacion LIKE '%SUPLENTE%' THEN 'Suplente'
                ELSE 'Principal'
            END
        ";
    }

    return "'Principal'";
}

function obtenerDatosDocente(PDO $pdo, $dni, $ciclo) {
    // Buscar nombre del ciclo en la tabla periodos
    $nombre_ciclo_real = $ciclo; // Fallback
    try {
        $stmtPeriodo = $pdo->query("SELECT inicio_ciclo, fin_ciclo FROM periodos LIMIT 1");
        if ($rowPeriodo = $stmtPeriodo->fetch()) {
            $inicio = trim($rowPeriodo['inicio_ciclo']);
            $fin = trim($rowPeriodo['fin_ciclo']);
            if (!empty($inicio) && !empty($fin)) {
                $nombre_ciclo_real = $inicio . ' - ' . $fin;
            } elseif (!empty($inicio)) {
                $nombre_ciclo_real = $inicio;
            }
        }
    } catch (Exception $e) {
        // Ignorar si no existe la tabla o campos y usar el fallback
    }

    $caseSuplente = obtenerCaseSuplente($pdo);

    $sql = "
    SELECT 
        d.nro_documento AS dni,
        d.nombres, 
        d.paterno, 
        d.materno, 
        d.celular,
        SUM(a.cantidad_horas) AS total_horas,
        
        GROUP_CONCAT(DISTINCT s.denominacion SEPARATOR ' | ') AS sedes,
        GROUP_CONCAT(DISTINCT c.denominacion SEPARATOR ' | ') AS cursos,
        GROUP_CONCAT(DISTINCT ar_grupo.denominacion SEPARATOR ' | ') AS areas,

        GROUP_CONCAT(DISTINCT 
            CONCAT(
                ar_grupo.denominacion, ':', c.denominacion, ' (',
                $caseSuplente,
                ')'
            )
            ORDER BY ar_grupo.denominacion, c.denominacion SEPARATOR ', '
        ) AS areas_cursos_detallados

    FROM asistencia_docentes a
    JOIN docentes d ON a.docentes_id = d.id
    JOIN carga_academicas ca ON a.carga_academicas_id = ca.id
    JOIN cursos c ON ca.cursos_id = c.id
    JOIN grupo_aulas ga ON ca.grupo_aulas_id = ga.id
    JOIN grupos g ON ga.grupos_id = g.id
    JOIN areas ar_grupo ON ga.areas_id = ar_grupo.id
    JOIN turnos t ON ga.turnos_id = t.id
    JOIN aulas au ON ga.aulas_id = au.id
    JOIN locales l ON au.locales_id = l.id
    JOIN sedes s ON l.sedes_id = s.id

    WHERE d.nro_documento = ?

    GROUP BY d.id, d.nro_documento, d.nombres, d.paterno, d.materno, d.celular
    ORDER BY d.paterno, d.materno, d.nombres
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dni]);

    $resultado = $stmt->fetchAll();

    if (count($resultado) > 0) {
        $row = $resultado[0];
        // En el ejemplo la PDF espera: VILCA CALIZAYA KEYKO FANNY (Paterno Materno Nombres)
        $nombreCompleto = trim($row['paterno'] . ' ' . $row['materno'] . ' ' . $row['nombres']);
        return [
            'nombre' => strtoupper($nombreCompleto),
            'dni' => $row['dni'],
            'ciclo' => strtoupper($nombre_ciclo_real),
            'sedes' => $row['sedes'],
            'cursos' => $row['cursos'],
            'areas' => $row['areas'],
            'horas' => $row['total_horas']
        ];
    }
    
    return null;
}
?>
