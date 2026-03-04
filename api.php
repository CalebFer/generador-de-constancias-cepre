<?php
// api.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexion.php';
use setasign\Fpdi\Tcpdf\Fpdi;

// Permitir solicitudes (CORS si fuera necesario)
header("Access-Control-Allow-Origin: *");

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'generar_pdf') {
    $dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';
    $ciclo = isset($_GET['ciclo']) ? trim($_GET['ciclo']) : '';
    $fecha_expedicion = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('d/m/Y');

    if (empty($dni) || empty($ciclo)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros.']);
        exit;
    }

    try {
        $pdo = obtenerConexion($ciclo);
        $docente_imprimir = obtenerDatosDocente($pdo, $dni, $ciclo);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    if (!$docente_imprimir) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No se encontró la constancia para el DNI y Ciclo indicados.']);
        exit;
    }

    // Agregar la fecha provista por el frontend (Lima)
    $docente_imprimir['fecha_expedicion'] = $fecha_expedicion;

    $archivo_fondo = __DIR__ . '/fondo_2026.pdf';
    if (!file_exists($archivo_fondo)) {
        if (file_exists(__DIR__ . '/fondo_2025.pdf')) {
            $archivo_fondo = __DIR__ . '/fondo_2025.pdf';
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Falta el archivo PDF de fondo en el servidor.']);
            exit;
        }
    }

    $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetMargins(25, 0, 25);

    $pdf->AddPage();
    $pageCount = $pdf->setSourceFile($archivo_fondo);
    $tplId = $pdf->importPage(1);
    $pdf->useTemplate($tplId, 0, 0, 210, 297, true);

    $pdf->SetY(40);
    $pdf->SetFont('helvetica', 'BU', 36);
    $pdf->Cell(0, 10, 'CONSTANCIA', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', 'B', 13);
    $parrafo_suscribe = "LA QUE SUSCRIBE, PRESIDENTE DEL CENTRO PREUNIVERSITARIO\nDE LA UNIVERSIDAD NACIONAL DEL ALTIPLANO - PUNO.";
    $pdf->MultiCell(0, 6, $parrafo_suscribe, 0, 'C');
    $pdf->Ln(8);

    $pdf->SetFont('helvetica', '', 12);
    $html_datos = <<<EOD
<div style="text-align: justify; line-height: 1.5; text-indent: 40px;">
Que el(la) Sr(a).: <b>{$docente_imprimir['nombre']}</b>, identificado(a) con DNI N° <b>{$docente_imprimir['dni']}</b>, ha laborado como <b>DOCENTE</b> en el Centro Preuniversitario de la Universidad Nacional del Altiplano durante el ciclo académico <b>{$docente_imprimir['ciclo']}</b>, según el siguiente detalle:
</div>
EOD;
    $pdf->writeHTMLCell(0, 0, '', '', $html_datos, 0, 1, 0, true, 'J');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 12);
    $espacio_x = 45;

    $pdf->SetX($espacio_x);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(45, 8, 'SEDE(S)', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, ': ' . $docente_imprimir['sedes'], 0, 1);

    $pdf->SetX($espacio_x);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(45, 8, 'CURSO(S)', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, ': ' . $docente_imprimir['cursos'], 0, 1);

    $pdf->SetX($espacio_x);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(45, 8, 'ÁREA(S)', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, ': ' . $docente_imprimir['areas'], 0, 1);

    $pdf->SetX($espacio_x);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(45, 8, 'TOTAL HORA(S)', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, ': ' . $docente_imprimir['horas'] . ' horas', 0, 1);

    $pdf->Ln(8);

    $html_cumplimiento = <<<EOD
<div style="text-align: justify; line-height: 1.5; text-indent: 40px;">
Durante este período, el/la mencionado/a docente ha cumplido satisfactoriamente con sus funciones académicas, demostrando responsabilidad y compromiso con la formación preuniversitaria de nuestros estudiantes.
</div>
EOD;
    $pdf->writeHTMLCell(0, 0, '', '', $html_cumplimiento, 0, 1, 0, true, 'J');
    $pdf->Ln(6);

    $html_expedicion = <<<EOD
<div style="text-align: justify; line-height: 1.5; text-indent: 40px;">
Se expide la presente constancia a solicitud escrita del interesado(a) para los fines que estime conveniente.
</div>
EOD;
    $pdf->writeHTMLCell(0, 0, '', '', $html_expedicion, 0, 1, 0, true, 'J');

    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Puno, ' . $docente_imprimir['fecha_expedicion'], 0, 1, 'R');

    // Clean output buffer just in case
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Devolver el PDF
    $pdf->Output('Constancia_CEPREUNA_' . $docente_imprimir['dni'] . '.pdf', 'I');
    exit;
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    exit;
}
