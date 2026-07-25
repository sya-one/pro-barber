<?php
/**
 * Centralized Secure File Upload Helper
 * Used by all modules for consistent, secure file uploads
 */

function secureFileUpload($file_input_name, $target_dir, $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $max_size_mb = 10) {
    $response = [
        'success' => false,
        'filename' => null,
        'error' => null
    ];
    
    // Check if file was uploaded
    if (empty($_FILES[$file_input_name]['name'])) {
        $response['error'] = 'No file selected.';
        return $response;
    }
    
    // Check for upload errors
    if ($_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $response['error'] = $upload_errors[$_FILES[$file_input_name]['error']] ?? 'Unknown upload error';
        return $response;
    }
    
    $file_size = $_FILES[$file_input_name]['size'];
    $max_bytes = $max_size_mb * 1024 * 1024;
    
    // Check file size
    if ($file_size > $max_bytes) {
        $response['error'] = "File too large. Maximum {$max_size_mb}MB allowed.";
        return $response;
    }
    
    // Validate extension
    $ext = strtolower(pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        $response['error'] = "Invalid file type: '$ext'. Allowed: " . implode(', ', $allowed_extensions);
        return $response;
    }
    
    // Validate image content
    if (!@getimagesize($_FILES[$file_input_name]['tmp_name'])) {
        $response['error'] = 'Invalid image file. File is not a valid image.';
        return $response;
    }
    
    // Validate MIME type using fileinfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES[$file_input_name]['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mimes)) {
            $response['error'] = "Invalid MIME type: '$mime'. Only image files allowed.";
            return $response;
        }
    }
    
    // Create directory if needed
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0755, true)) {
            $response['error'] = 'Failed to create upload directory.';
            return $response;
        }
    }
    
    // Check directory is writable
    if (!is_writable($target_dir)) {
        $response['error'] = 'Upload directory is not writable.';
        return $response;
    }
    
    // Generate secure filename
    $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $full_path = $target_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $full_path)) {
        $response['error'] = 'Failed to save uploaded file.';
        return $response;
    }
    
    // Verify file was saved
    if (!file_exists($full_path)) {
        $response['error'] = 'File save verification failed.';
        return $response;
    }
    
    $response['success'] = true;
    $response['filename'] = $filename;
    return $response;
}

/**
 * Delete an uploaded file safely
 */
function deleteUploadedFile($target_dir, $filename) {
    if (empty($filename) || $filename === 'default.jpg' || $filename === 'default-product.png') {
        return false;
    }
    $full_path = $target_dir . $filename;
    if (file_exists($full_path)) {
        return @unlink($full_path);
    }
    return false;
}

/**
 * Get upload directory path (relative to project root)
 */
function getUploadPath($type) {
    $base = dirname(__DIR__);
    $paths = [
        'product' => $base . '/uploads/products/',
        'barber' => $base . '/uploads/barbers/',
        'service' => $base . '/uploads/services/',
        'expense' => $base . '/uploads/expenses/',
        'gallery' => $base . '/assets/gallery/',
        'user' => $base . '/uploads/users/'
    ];
    return $paths[$type] ?? $base . '/uploads/';
}
?>