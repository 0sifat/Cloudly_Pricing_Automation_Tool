<?php
session_start();

// Helper function to get base path for redirects
function getBasePath() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname(dirname($script));
    $path = rtrim($path, '/');
    return $path ? $path : '';
}

$base_path = getBasePath();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_path . "/views/login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/PDFGenerator.php';

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    die('Project ID is required');
}

$userModel = new User();
$projectModel = new Project();

$user = $userModel->getUserById($_SESSION['user_id']);
$project = $projectModel->getProject($project_id);

if (!$project) {
    die('Project not found');
}

// Check if user wants to download as PDF or view HTML
$format = $_GET['format'] ?? 'html';

if ($format === 'pdf') {
    // Try to use TCPDF if available
    $tcpdf_path = __DIR__ . '/../libs/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    }
}

// Generate PDF/HTML using PDFGenerator
$pdfGenerator = new PDFGenerator();
$pdf_path = $pdfGenerator->generateInvoice($project_id, $user, $project, $format);
