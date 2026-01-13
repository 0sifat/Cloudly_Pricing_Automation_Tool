<?php
/**
 * Simple HTML to PDF converter using browser print functionality
 * For production, use TCPDF or mPDF
 */

class HTML2PDF {
    public static function generatePDF($html, $filename) {
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // For now, output HTML that browser can convert to PDF
        // In production, use TCPDF or mPDF here
        echo $html;
        exit;
    }
}






