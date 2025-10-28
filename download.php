<?php
// Download and delete file
if (isset($_GET['file'])) {
    $file = basename($_GET['file']); // Security: prevent directory traversal
    $filePath = __DIR__ . '/uploads/' . $file;
    
    if (file_exists($filePath)) {
        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Read and output file
        readfile($filePath);
        
        // Delete file after download
        unlink($filePath);
        
        exit;
    } else {
        die('File not found.');
    }
} else {
    die('No file specified.');
}
