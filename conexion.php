<?php
// conexion.php

/**
 * Devuelve la configuracion de conexion por cada ciclo.
 *
 * @return array<string, array<string, mixed>>
 */
function obtenerConfiguracionesDeConexion() {
    $hostLocal = getenv('CEPRE_DB_HOST_LOCAL') ?: '127.0.0.1';
    $usuarioLocal = getenv('CEPRE_DB_USER_LOCAL') ?: 'admincepre';
    $passwordLocal = getenv('CEPRE_DB_PASS_LOCAL') ?: 'cepre2026';

    $hostRemoto = getenv('CEPRE_DB_HOST_REMOTO') ?: '138.68.226.228';
    $puertoRemoto = (int) (getenv('CEPRE_DB_PORT_REMOTO') ?: 3306);
    $usuarioRemoto = getenv('CEPRE_DB_USER_REMOTO') ?: 'vista';
    $passwordRemoto = getenv('CEPRE_DB_PASS_REMOTO') ?: '3DC35dd_oXb\\~FvuT';
    $baseRemota = getenv('CEPRE_DB_NAME_REMOTO') ?: 'cepreuna_production';

    $crearConfigLocal = function ($baseDeDatos) use ($hostLocal, $usuarioLocal, $passwordLocal) {
        return [
            'host' => $hostLocal,
            'usuario' => $usuarioLocal,
            'password' => $passwordLocal,
            'dbname' => $baseDeDatos,
        ];
    };

    return [
        'CEPRE_2022_1' => $crearConfigLocal('cepre'),
        'CEPRE_2022_2' => $crearConfigLocal('cepre_2022_2023'),
        'CEPRE_2023_1' => $crearConfigLocal('cepre_2023_1'),
        'CEPRE_2023_2' => $crearConfigLocal('cepre_2023_2'),
        'CEPRE_2024_1' => $crearConfigLocal('cepre_2024_1'),
        'CEPRE_2024_2' => $crearConfigLocal('cepre_2024_2'),
        'CEPRE_2025_1' => $crearConfigLocal('marzo_julio_2025'),
        'CEPRE_2026_1' => [
            'host' => $hostRemoto,
            'port' => $puertoRemoto,
            'usuario' => $usuarioRemoto,
            'password' => $passwordRemoto,
            'dbname' => $baseRemota,
        ],
    ];
}

/**
 * Devuelve el arreglo con todas las bases de datos configuradas para cada ciclo.
 *
 * @return array Arreglo asociativo de Ciclo => NombreBD
 */
function obtenerBasesDeDatos() {
    $basesDeDatos = [];

    foreach (obtenerConfiguracionesDeConexion() as $ciclo => $configuracion) {
        $basesDeDatos[$ciclo] = $configuracion['dbname'];
    }

    return $basesDeDatos;
}

/**
 * Devuelve los nombres de los ciclos (claves) que estan configurados.
 *
 * @return array
 */
function obtenerCiclosDisponibles() {
    return array_keys(obtenerConfiguracionesDeConexion());
}

/**
 * Funcion para obtener la conexion a la base de datos dependiendo del ciclo.
 *
 * @param string $ciclo El nombre del ciclo academico.
 * @return PDO La instancia de conexion PDO.
 * @throws Exception Si no hay configuracion para el ciclo o falla la conexion.
 */
function obtenerConexion($ciclo) {
    $configuraciones = obtenerConfiguracionesDeConexion();

    if (!isset($configuraciones[$ciclo])) {
        throw new Exception('No existe una base de datos configurada para el ciclo: ' . $ciclo);
    }

    $configuracion = $configuraciones[$ciclo];
    $host = $configuracion['host'];
    $usuario = $configuracion['usuario'];
    $password = $configuracion['password'];
    $db_nombre = $configuracion['dbname'];
    $puerto = isset($configuracion['port']) ? ';port=' . $configuracion['port'] : '';

    try {
        $dsn = "mysql:host=$host$puerto;dbname=$db_nombre;charset=utf8mb4";

        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $usuario, $password, $opciones);
    } catch (PDOException $e) {
        throw new Exception("Error de conexion a la base de datos '$db_nombre': " . $e->getMessage());
    }
}

function existeColumna(PDO $pdo, $tabla, $columna) {
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
    $nombre_ciclo_real = $ciclo;

    try {
        $consultaPeriodo = existeColumna($pdo, 'periodos', 'anio')
            ? 'SELECT inicio_ciclo, fin_ciclo, anio FROM periodos ORDER BY id DESC LIMIT 1'
            : 'SELECT inicio_ciclo, fin_ciclo FROM periodos ORDER BY id DESC LIMIT 1';

        $stmtPeriodo = $pdo->query($consultaPeriodo);
        if ($rowPeriodo = $stmtPeriodo->fetch()) {
            $inicio = trim($rowPeriodo['inicio_ciclo']);
            $fin = trim($rowPeriodo['fin_ciclo']);
            $anio = isset($rowPeriodo['anio']) ? trim((string) $rowPeriodo['anio']) : '';

            if (!empty($inicio) && !empty($fin)) {
                $nombre_ciclo_real = trim($inicio . ' - ' . $fin . (!empty($anio) ? ' ' . $anio : ''));
            } elseif (!empty($inicio)) {
                $nombre_ciclo_real = trim($inicio . (!empty($anio) ? ' ' . $anio : ''));
            } elseif (!empty($anio)) {
                $nombre_ciclo_real = $anio;
            }
        }
    } catch (Exception $e) {
        // Si la tabla periodos no esta disponible, usamos el ciclo recibido.
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
