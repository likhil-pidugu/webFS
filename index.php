<?php
session_start();
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);

// Security settings
$allowed_extensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'md', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'zip', 'rar', '7z', 'mp3', 'mp4', 'avi', 'mov', 'mkv', 'webm', 'xlsx', 'doc', 'docx', 'py', 'ico', 'ps1', 'ovpn', 'm4a', 'flac', 'wav'];
$max_file_size = 50 * 1024 * 1024; // 50MB
$base_path = realpath(__DIR__);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'list_directory':
            $path = $_POST['path'] ?? '';
            $full_path = $base_path;
            
            if (!empty($path)) {
                $full_path = realpath($base_path . '/' . ltrim($path, '/'));
            }
            
            if ($full_path && strpos($full_path, $base_path) === 0 && is_dir($full_path)) {
                $items = getDirectoryContents($full_path, $base_path);
                $relative_path = str_replace($base_path, '', $full_path);
                $relative_path = str_replace('\\', '/', $relative_path);
                $relative_path = ltrim($relative_path, '/');
                
                $response = [
                    'success' => true, 
                    'items' => $items, 
                    'path' => $relative_path,
                    'full_path' => $full_path
                ];
            } else {
                $response = ['success' => false, 'message' => 'Invalid directory path'];
            }
            break;
            
        case 'upload':
            if (isset($_FILES['files'])) {
                $upload_path = $_POST['path'] ?? '';
                $target_dir = $base_path;
                
                if (!empty($upload_path)) {
                    $target_dir = realpath($base_path . '/' . ltrim($upload_path, '/'));
                }
                
                if ($target_dir && strpos($target_dir, $base_path) === 0 && is_dir($target_dir) && is_writable($target_dir)) {
                    $uploaded = 0;
                    $total = count($_FILES['files']['tmp_name']);
                    
                    for ($i = 0; $i < $total; $i++) {
                        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                            $filename = basename($_FILES['files']['name'][$i]);
                            $target_file = $target_dir . DIRECTORY_SEPARATOR . $filename;
                            
                            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target_file)) {
                                $uploaded++;
                            }
                        }
                    }
                    $response = ['success' => true, 'message' => "$uploaded of $total files uploaded successfully"];
                } else {
                    $response = ['success' => false, 'message' => 'Invalid upload directory or not writable'];
                }
            } else {
                $response = ['success' => false, 'message' => 'No files received'];
            }
            break;
            
        case 'delete':
            $path = $_POST['path'] ?? '';
            $full_path = realpath($base_path . '/' . ltrim($path, '/'));
            
            if ($full_path && strpos($full_path, $base_path) === 0 && $full_path !== $base_path) {
                if (is_file($full_path)) {
                    $success = unlink($full_path);
                    $response = ['success' => $success, 'message' => $success ? 'File deleted successfully' : 'Failed to delete file'];
                } elseif (is_dir($full_path)) {
                    $success = deleteDirectory($full_path);
                    $response = ['success' => $success, 'message' => $success ? 'Directory deleted successfully' : 'Failed to delete directory'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid file path'];
            }
            break;
            
        case 'create_folder':
            $path = $_POST['path'] ?? '';
            $name = $_POST['name'] ?? '';
            
            if (empty($name)) {
                $response = ['success' => false, 'message' => 'Folder name is required'];
                break;
            }
            
            $parent_dir = $base_path;
            if (!empty($path)) {
                $parent_dir = realpath($base_path . '/' . ltrim($path, '/'));
            }
            
            if ($parent_dir && strpos($parent_dir, $base_path) === 0 && is_dir($parent_dir)) {
                $new_folder = $parent_dir . DIRECTORY_SEPARATOR . $name;
                
                if (!file_exists($new_folder)) {
                    $success = mkdir($new_folder, 0755, true);
                    $response = ['success' => $success, 'message' => $success ? 'Folder created successfully' : 'Failed to create folder'];
                } else {
                    $response = ['success' => false, 'message' => 'Folder already exists'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid parent directory'];
            }
            break;
            
        case 'rename':
            $old_path = $_POST['old_path'] ?? '';
            $new_name = $_POST['new_name'] ?? '';
            
            if (empty($new_name)) {
                $response = ['success' => false, 'message' => 'New name is required'];
                break;
            }
            
            $old_full_path = realpath($base_path . '/' . ltrim($old_path, '/'));
            
            if ($old_full_path && strpos($old_full_path, $base_path) === 0) {
                $new_full_path = dirname($old_full_path) . DIRECTORY_SEPARATOR . $new_name;
                
                if (!file_exists($new_full_path)) {
                    $success = rename($old_full_path, $new_full_path);
                    $response = ['success' => $success, 'message' => $success ? 'Renamed successfully' : 'Failed to rename'];
                } else {
                    $response = ['success' => false, 'message' => 'A file with that name already exists'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid file path'];
            }
            break;
            
        case 'copy':
            $source_path = $_POST['source_path'] ?? '';
            $target_path = $_POST['target_path'] ?? '';
            
            $source_full = realpath($base_path . '/' . ltrim($source_path, '/'));
            $target_dir = realpath($base_path . '/' . ltrim($target_path, '/'));
            
            if ($source_full && $target_dir && 
                strpos($source_full, $base_path) === 0 && 
                strpos($target_dir, $base_path) === 0 && 
                is_dir($target_dir)) {
                
                $filename = basename($source_full);
                $target_full = $target_dir . DIRECTORY_SEPARATOR . $filename;
                
                if (is_file($source_full)) {
                    $success = copy($source_full, $target_full);
                    $response = ['success' => $success, 'message' => $success ? 'File copied successfully' : 'Failed to copy file'];
                } else {
                    $response = ['success' => false, 'message' => 'Directory copying not implemented'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid source or target path'];
            }
            break;
            
        case 'move':
            $source_path = $_POST['source_path'] ?? '';
            $target_path = $_POST['target_path'] ?? '';
            
            $source_full = realpath($base_path . '/' . ltrim($source_path, '/'));
            $target_dir = realpath($base_path . '/' . ltrim($target_path, '/'));
            
            if ($source_full && $target_dir && 
                strpos($source_full, $base_path) === 0 && 
                strpos($target_dir, $base_path) === 0 && 
                is_dir($target_dir)) {
                
                $filename = basename($source_full);
                $target_full = $target_dir . DIRECTORY_SEPARATOR . $filename;
                
                $success = rename($source_full, $target_full);
                $response = ['success' => $success, 'message' => $success ? 'File moved successfully' : 'Failed to move file'];
            } else {
                $response = ['success' => false, 'message' => 'Invalid source or target path'];
            }
            break;
            
        case 'get_file_content':
            $file_path = $_POST['file_path'] ?? '';
            $full_path = realpath($base_path . '/' . ltrim($file_path, '/'));
            
            if ($full_path && strpos($full_path, $base_path) === 0 && is_file($full_path)) {
                $content = file_get_contents($full_path);
                $response = ['success' => true, 'content' => $content, 'size' => filesize($full_path)];
            } else {
                $response = ['success' => false, 'message' => 'File not found or not accessible'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Handle file download
if (isset($_GET['download'])) {
    $file_path = realpath($base_path . '/' . ltrim($_GET['download'], '/'));
    
    if ($file_path && strpos($file_path, $base_path) === 0 && is_file($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

// Get directory contents
function getDirectoryContents($full_path, $base_path) {
    $items = [];
    
    if (!is_dir($full_path) || !is_readable($full_path)) {
        return $items;
    }
    
    $files = scandir($full_path);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        // Skip system files
        if (in_array($file, ['hiberfil.sys', 'pagefile.sys', 'swapfile.sys', 'DumpStack.log.tmp'])) continue;
        
        $file_path = $full_path . DIRECTORY_SEPARATOR . $file;
        
        // Skip if not readable
        if (!is_readable($file_path)) continue;
        
        $relative_path = str_replace($base_path, '', $file_path);
        $relative_path = str_replace('\\', '/', $relative_path);
        $relative_path = ltrim($relative_path, '/');
        
        $item = [
            'name' => $file,
            'path' => $relative_path,
            'type' => is_dir($file_path) ? 'folder' : 'file',
            'size' => is_file($file_path) ? filesize($file_path) : 0,
            'modified' => filemtime($file_path),
            'extension' => is_file($file_path) ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '',
            'readable' => is_readable($file_path),
            'writable' => is_writable($file_path)
        ];
        
        $items[] = $item;
    }
    
    // Sort: folders first, then by name
    usort($items, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Get initial directory contents
$current_path_left = $_GET['left'] ?? '';
$current_path_right = $_GET['right'] ?? '';

$left_full_path = $base_path;
if (!empty($current_path_left)) {
    $left_full_path = realpath($base_path . '/' . ltrim($current_path_left, '/'));
}

$right_full_path = $base_path;
if (!empty($current_path_right)) {
    $right_full_path = realpath($base_path . '/' . ltrim($current_path_right, '/'));
}

$files_left = getDirectoryContents($left_full_path ?: $base_path, $base_path);
$files_right = getDirectoryContents($right_full_path ?: $base_path, $base_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web File System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .toolbar {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-ghost:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .theme-toggle {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle:hover {
            background: var(--border-color);
        }

        .main-container {
            display: flex;
            height: calc(100vh - 80px);
            gap: 1px;
            background: var(--border-color);
        }

        .pane {
            flex: 1;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .pane-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .path-input-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .path-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .path-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }

        .breadcrumb-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .file-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
            user-select: none;
        }

        .file-item:hover {
            background: var(--bg-secondary);
        }

        .file-item.selected {
            background: rgba(37, 99, 235, 0.1);
            border-color: var(--primary-color);
        }

        .file-icon {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .file-icon.folder {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .file-icon.file {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
        }

        .file-icon.image {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }

        .file-icon.code {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .file-icon.archive {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            gap: 1rem;
        }

        .context-menu {
            position: fixed;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            padding: 0.5rem 0;
            z-index: 1000;
            min-width: 200px;
        }

        .context-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .context-menu-item:hover {
            background: var(--bg-secondary);
        }

        .context-menu-item.danger {
            color: var(--danger-color);
        }

        .context-menu-separator {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            margin: 1rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: 0.75rem;
            padding: 1.5rem;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .status-bar {
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            z-index: 2000;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background: var(--secondary-color);
            color: white;
        }

        .notification.error {
            background: var(--danger-color);
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .preview-content {
            max-width: 100%;
            max-height: 70vh;
            overflow: auto;
        }

        .preview-content img {
            max-width: 100%;
            height: auto;
        }

        .preview-content video {
            max-width: 100%;
            height: auto;
        }

        .preview-content audio {
            width: 100%;
        }

        .preview-content pre {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                height: auto;
                min-height: calc(100vh - 80px);
            }
            
            .pane {
                min-height: 50vh;
            }
            
            .header-content {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .toolbar {
                justify-content: center;
            }
            
            .file-meta {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .file-item {
            animation: fadeIn 0.2s ease-out;
        }

        /* Loading state */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sort-controls {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .sort-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.25rem;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .sort-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .sort-btn:hover {
            background: var(--bg-tertiary);
        }
    </style>
</head>
<body data-theme="light">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <span onClick="window.location.href='/';">📁</span>
                <span onClick="window.location.href='/';">WebFS</span>
            </div>
            
            <div class="toolbar">
                <button class="btn btn-primary" onclick="showUploadModal()">
                    <span>📤</span> Upload
                </button>
                <button class="btn btn-secondary" onclick="showCreateFolderModal()">
                    <span>📁</span> New Folder
                </button>
                <button class="btn btn-ghost" onclick="refreshPanes()">
                    <span>🔄</span> Refresh
                </button>
                <button class="theme-toggle" onclick="toggleTheme()">
                    <span id="theme-icon">🌙</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Left Pane -->
        <div class="pane" id="left-pane">
            <div class="pane-header">
                <div class="path-input-container">
                    <input type="text" class="path-input" id="left-path-input" placeholder="Enter path..." value="<?= htmlspecialchars($current_path_left) ?>">
                    <button class="btn btn-ghost" onclick="navigateToPath('left')">Go</button>
                </div>
                <div class="sort-controls">
                    <button class="sort-btn active" onclick="sortFiles('left', 'name')">Name</button>
                    <button class="sort-btn" onclick="sortFiles('left', 'type')">Type</button>
                    <button class="sort-btn" onclick="sortFiles('left', 'size')">Size</button>
                    <button class="sort-btn" onclick="sortFiles('left', 'date')">Date</button>
                    <button class="sort-btn" onclick="toggleSortOrder('left')">↕️</button>
                </div>
                <div class="breadcrumb" id="left-breadcrumb">
                    <span class="breadcrumb-item" onclick="navigatePane('left', '')">🏠 Root</span>
                    <?php if ($current_path_left): ?>
                        <?php 
                        $segments = explode('/', trim($current_path_left, '/'));
                        $path = '';
                        foreach ($segments as $segment): 
                            if (empty($segment)) continue;
                            $path .= ($path ? '/' : '') . $segment;
                        ?>
                            <span>›</span>
                            <span class="breadcrumb-item" onclick="navigatePane('left', '<?= htmlspecialchars($path) ?>')"><?= htmlspecialchars($segment) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="file-list" id="left-files">
                <?php foreach ($files_left as $file): ?>
                    <div class="file-item" 
                         data-path="<?= htmlspecialchars($file['path']) ?>"
                         data-type="<?= $file['type'] ?>"
                         data-name="<?= htmlspecialchars($file['name']) ?>"
                         data-size="<?= $file['size'] ?>"
                         data-modified="<?= $file['modified'] ?>"
                         data-extension="<?= htmlspecialchars($file['extension']) ?>"
                         onclick="selectFile(this, event)"
                         ondblclick="handleDoubleClick('<?= htmlspecialchars($file['path']) ?>', '<?= $file['type'] ?>', 'left')"
                         oncontextmenu="showContextMenu(event, this)"
                         draggable="true"
                         ondragstart="handleDragStart(event, this)"
                         ondragover="handleDragOver(event, this)"
                         ondrop="handleDrop(event, this, 'left')">
                        
                        <div class="file-icon <?= getFileIconClass($file) ?>">
                            <?= getFileIcon($file) ?>
                        </div>
                        
                        <div class="file-info">
                            <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                            <div class="file-meta">
                                <span><?= $file['type'] === 'file' ? formatFileSize($file['size']) : 'Folder' ?></span>
                                <span><?= date('M j, Y H:i', $file['modified']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Pane -->
        <div class="pane" id="right-pane">
            <div class="pane-header">
                <div class="path-input-container">
                    <input type="text" class="path-input" id="right-path-input" placeholder="Enter path..." value="<?= htmlspecialchars($current_path_right) ?>">
                    <button class="btn btn-ghost" onclick="navigateToPath('right')">Go</button>
                </div>
                <div class="sort-controls">
                    <button class="sort-btn active" onclick="sortFiles('right', 'name')">Name</button>
                    <button class="sort-btn" onclick="sortFiles('right', 'type')">Type</button>
                    <button class="sort-btn" onclick="sortFiles('right', 'size')">Size</button>
                    <button class="sort-btn" onclick="sortFiles('right', 'date')">Date</button>
                    <button class="sort-btn" onclick="toggleSortOrder('right')">↕️</button>
                </div>
                <div class="breadcrumb" id="right-breadcrumb">
                    <span class="breadcrumb-item" onclick="navigatePane('right', '')">🏠 Root</span>
                    <?php if ($current_path_right): ?>
                        <?php 
                        $segments = explode('/', trim($current_path_right, '/'));
                        $path = '';
                        foreach ($segments as $segment): 
                            if (empty($segment)) continue;
                            $path .= ($path ? '/' : '') . $segment;
                        ?>
                            <span>›</span>
                            <span class="breadcrumb-item" onclick="navigatePane('right', '<?= htmlspecialchars($path) ?>')"><?= htmlspecialchars($segment) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="file-list" id="right-files">
                <?php foreach ($files_right as $file): ?>
                    <div class="file-item" 
                         data-path="<?= htmlspecialchars($file['path']) ?>"
                         data-type="<?= $file['type'] ?>"
                         data-name="<?= htmlspecialchars($file['name']) ?>"
                         data-size="<?= $file['size'] ?>"
                         data-modified="<?= $file['modified'] ?>"
                         data-extension="<?= htmlspecialchars($file['extension']) ?>"
                         onclick="selectFile(this, event)"
                         ondblclick="handleDoubleClick('<?= htmlspecialchars($file['path']) ?>', '<?= $file['type'] ?>', 'right')"
                         oncontextmenu="showContextMenu(event, this)"
                         draggable="true"
                         ondragstart="handleDragStart(event, this)"
                         ondragover="handleDragOver(event, this)"
                         ondrop="handleDrop(event, this, 'right')">
                        
                        <div class="file-icon <?= getFileIconClass($file) ?>">
                            <?= getFileIcon($file) ?>
                        </div>
                        
                        <div class="file-info">
                            <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                            <div class="file-meta">
                                <span><?= $file['type'] === 'file' ? formatFileSize($file['size']) : 'Folder' ?></span>
                                <span><?= date('M j, Y H:i', $file['modified']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="status-bar">
        <span id="status-text">Ready</span>
        <span id="file-count"><?= count($files_left) + count($files_right) ?> items total</span>
    </div>

    <!-- Context Menu -->
    <div class="context-menu" id="context-menu" style="display: none;">
        <div class="context-menu-item" onclick="downloadFile(contextMenuTarget.dataset.path)">
            <span>📥</span> Download
        </div>
        <div class="context-menu-item" onclick="previewFile(contextMenuTarget.dataset.path)">
            <span>👁️</span> Preview
        </div>
        <div class="context-menu-item" onclick="showRenameModal()">
            <span>✏️</span> Rename
        </div>
        <div class="context-menu-item" onclick="copyFile()">
            <span>📋</span> Copy
        </div>
        <div class="context-menu-item" onclick="cutFile()">
            <span>✂️</span> Cut
        </div>
        <div class="context-menu-separator"></div>
        <div class="context-menu-item danger" onclick="deleteFile(contextMenuTarget.dataset.path)">
            <span>🗑️</span> Delete
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="upload-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">Upload Files</div>
            <div class="upload-area" id="upload-area" onclick="document.getElementById('file-input').click()">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📤</div>
                <div>Drop files here or click to select</div>
                <input type="file" id="file-input" multiple style="display: none;" onchange="handleFileSelect(this.files)">
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="hideModal('upload-modal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="create-folder-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">Create New Folder</div>
            <div class="form-group">
                <label class="form-label">Folder Name</label>
                <input type="text" class="form-input" id="folder-name" placeholder="Enter folder name">
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="hideModal('create-folder-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createFolder()">Create</button>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal" id="rename-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">Rename Item</div>
            <div class="form-group">
                <label class="form-label">New Name</label>
                <input type="text" class="form-input" id="rename-input" placeholder="Enter new name">
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost" onclick="hideModal('rename-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="renameFile()">Rename</button>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal" id="preview-modal" style="display: none;">
        <div class="modal-content" style="max-width: 80vw; max-height: 90vh;">
            <div class="modal-header">
                <span id="preview-title">File Preview</span>
                <button class="btn btn-ghost" onclick="hideModal('preview-modal')" style="margin-left: auto;">✕</button>
            </div>
            <div class="preview-content" id="preview-content">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let contextMenuTarget = null;
        let selectedFiles = new Set();
        let currentPane = 'left';
        let clipboard = null;
        let draggedItem = null;
        let sortOrder = { left: 'asc', right: 'asc' };
        let sortBy = { left: 'name', right: 'name' };

        // Theme management
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.setAttribute('data-theme', newTheme);
            themeIcon.textContent = newTheme === 'light' ? '🌙' : '☀️';
            localStorage.setItem('theme', newTheme);
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            document.getElementById('theme-icon').textContent = savedTheme === 'light' ? '🌙' : '☀️';
            
            setupDragAndDrop();
            setupKeyboardShortcuts();
        });

        // Navigation functions
        function navigatePane(pane, path) {
            const url = new URL(window.location);
            url.searchParams.set(pane, path);
            window.location.href = url.toString();
        }

        function navigateToPath(pane) {
            const input = document.getElementById(pane + '-path-input');
            const path = input.value.trim();
            navigatePane(pane, path);
        }

        function handleDoubleClick(path, type, pane) {
            if (type === 'folder') {
                navigatePane(pane, path);
            } else {
                downloadFile(path);
            }
        }

        function refreshPanes() {
            window.location.reload();
        }

        // File selection
        function selectFile(element, event) {
            const isCtrlPressed = event.ctrlKey || event.metaKey;
            
            if (!isCtrlPressed) {
                document.querySelectorAll('.file-item.selected').forEach(item => {
                    item.classList.remove('selected');
                });
                selectedFiles.clear();
            }
            
            element.classList.toggle('selected');
            const path = element.dataset.path;
            
            if (selectedFiles.has(path)) {
                selectedFiles.delete(path);
            } else {
                selectedFiles.add(path);
            }
            
            updateStatus();
        }

        function updateStatus() {
            const statusText = document.getElementById('status-text');
            if (selectedFiles.size > 0) {
                statusText.textContent = `${selectedFiles.size} item(s) selected`;
            } else {
                statusText.textContent = 'Ready';
            }
        }

        // Context menu
        function showContextMenu(event, element) {
            event.preventDefault();
            contextMenuTarget = element;
            
            const contextMenu = document.getElementById('context-menu');
            contextMenu.style.display = 'block';
            contextMenu.style.left = event.pageX + 'px';
            contextMenu.style.top = event.pageY + 'px';
            
            // Hide context menu when clicking elsewhere
            setTimeout(() => {
                document.addEventListener('click', hideContextMenu);
            }, 0);
        }

        function hideContextMenu() {
            document.getElementById('context-menu').style.display = 'none';
            document.removeEventListener('click', hideContextMenu);
        }

        // File operations
        function downloadFile(path) {
            window.open(`?download=${encodeURIComponent(path)}`, '_blank');
        }

        async function deleteFile(path) {
            if (confirm('Are you sure you want to delete this item?')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('path', path);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification('Item deleted successfully', 'success');
                        refreshPanes();
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                } catch (error) {
                    showNotification('Error: ' + error.message, 'error');
                }
            }
        }

        function copyFile() {
            clipboard = {
                path: contextMenuTarget.dataset.path,
                operation: 'copy'
            };
            showNotification('File copied to clipboard', 'success');
        }

        function cutFile() {
            clipboard = {
                path: contextMenuTarget.dataset.path,
                operation: 'cut'
            };
            showNotification('File cut to clipboard', 'success');
        }

        async function pasteFile(targetPath) {
            if (!clipboard) {
                showNotification('Nothing to paste', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', clipboard.operation === 'copy' ? 'copy' : 'move');
                formData.append('source_path', clipboard.path);
                formData.append('target_path', targetPath);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    if (clipboard.operation === 'cut') {
                        clipboard = null;
                    }
                    refreshPanes();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Drag and drop
        function handleDragStart(event, element) {
            draggedItem = {
                path: element.dataset.path,
                type: element.dataset.type,
                name: element.dataset.name
            };
            element.style.opacity = '0.5';
        }

        function handleDragOver(event, element) {
            event.preventDefault();
            if (element.dataset.type === 'folder' && draggedItem && draggedItem.path !== element.dataset.path) {
                element.style.backgroundColor = 'rgba(37, 99, 235, 0.1)';
            }
        }

        function handleDrop(event, element, pane) {
            event.preventDefault();
            element.style.backgroundColor = '';
            
            if (draggedItem && element.dataset.type === 'folder' && draggedItem.path !== element.dataset.path) {
                moveFile(draggedItem.path, element.dataset.path);
            }
            
            // Reset dragged item
            if (draggedItem) {
                const draggedElement = document.querySelector(`[data-path="${draggedItem.path}"]`);
                if (draggedElement) {
                    draggedElement.style.opacity = '1';
                }
                draggedItem = null;
            }
        }

        async function moveFile(sourcePath, targetPath) {
            try {
                const formData = new FormData();
                formData.append('action', 'move');
                formData.append('source_path', sourcePath);
                formData.append('target_path', targetPath);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('File moved successfully', 'success');
                    refreshPanes();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Modals
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showUploadModal() {
            currentPane = 'left'; // Default to left pane
            showModal('upload-modal');
        }

        function showCreateFolderModal() {
            currentPane = 'left'; // Default to left pane
            showModal('create-folder-modal');
        }

        function showRenameModal() {
            const input = document.getElementById('rename-input');
            input.value = contextMenuTarget.dataset.name;
            showModal('rename-modal');
        }

        // File upload
        function setupDragAndDrop() {
            const uploadArea = document.getElementById('upload-area');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });

            uploadArea.addEventListener('drop', handleUploadDrop, false);
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            document.getElementById('upload-area').classList.add('dragover');
        }

        function unhighlight(e) {
            document.getElementById('upload-area').classList.remove('dragover');
        }

        function handleUploadDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFileSelect(files);
        }

        async function handleFileSelect(files) {
            if (files.length === 0) return;
            
            const currentPath = getCurrentPath();
            
            try {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('path', currentPath);
                
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]);
                }
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    hideModal('upload-modal');
                    refreshPanes();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function createFolder() {
            const name = document.getElementById('folder-name').value.trim();
            if (!name) {
                showNotification('Please enter a folder name', 'error');
                return;
            }
            
            const currentPath = getCurrentPath();
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_folder');
                formData.append('path', currentPath);
                formData.append('name', name);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    hideModal('create-folder-modal');
                    document.getElementById('folder-name').value = '';
                    refreshPanes();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function renameFile() {
            const newName = document.getElementById('rename-input').value.trim();
            if (!newName) {
                showNotification('Please enter a new name', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'rename');
                formData.append('old_path', contextMenuTarget.dataset.path);
                formData.append('new_name', newName);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    hideModal('rename-modal');
                    refreshPanes();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Preview functionality
        async function previewFile(path) {
            const extension = path.split('.').pop().toLowerCase();
            const previewModal = document.getElementById('preview-modal');
            const previewTitle = document.getElementById('preview-title');
            const previewContent = document.getElementById('preview-content');
            
            previewTitle.textContent = path.split('/').pop();
            previewContent.innerHTML = '<div class="loading"><div class="spinner"></div>Loading...</div>';
            
            showModal('preview-modal');
            
            // Image files
            if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'].includes(extension)) {
                previewContent.innerHTML = `<img src="?download=${encodeURIComponent(path)}" alt="Preview" style="max-width: 100%; height: auto;">`;
            }
            // Video files
            else if (['mp4', 'webm', 'avi', 'mov', 'mkv'].includes(extension)) {
                previewContent.innerHTML = `<video controls style="max-width: 100%; height: auto;">
                    <source src="?download=${encodeURIComponent(path)}" type="video/${extension}">
                    Your browser does not support the video tag.
                </video>`;
            }
            // Audio files
            else if (['mp3', 'm4a', 'wav', 'flac'].includes(extension)) {
                previewContent.innerHTML = `<audio controls style="width: 100%;">
                    <source src="?download=${encodeURIComponent(path)}" type="audio/${extension}">
                    Your browser does not support the audio tag.
                </audio>`;
            }
            // PDF files
            else if (extension === 'pdf') {
                previewContent.innerHTML = `<iframe src="?download=${encodeURIComponent(path)}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
            }
            // Text files
            else if (['txt', 'html', 'css', 'js', 'php', 'py', 'json', 'xml', 'md', 'ps1', 'ovpn'].includes(extension)) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_file_content');
                    formData.append('file_path', path);
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        previewContent.innerHTML = `<pre style="white-space: pre-wrap; font-family: 'Courier New', monospace; background: var(--bg-secondary); padding: 1rem; border-radius: 0.5rem; overflow-x: auto;">${escapeHtml(data.content)}</pre>`;
                    } else {
                        previewContent.innerHTML = '<p>Error loading file content</p>';
                    }
                } catch (error) {
                    previewContent.innerHTML = '<p>Error loading file content</p>';
                }
            }
            // Other files
            else {
                previewContent.innerHTML = `<div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                    <p>Preview not available for this file type</p>
                    <button class="btn btn-primary" onclick="downloadFile('${path}')" style="margin-top: 1rem;">Download File</button>
                </div>`;
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Sorting
        function sortFiles(pane, criteria) {
            sortBy[pane] = criteria;
            
            // Update active sort button
            const sortButtons = document.querySelectorAll(`#${pane}-pane .sort-btn`);
            sortButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            applySorting(pane);
        }

        function toggleSortOrder(pane) {
            sortOrder[pane] = sortOrder[pane] === 'asc' ? 'desc' : 'asc';
            event.target.textContent = sortOrder[pane] === 'asc' ? '↑' : '↓';
            applySorting(pane);
        }

        function applySorting(pane) {
            const fileList = document.getElementById(pane + '-files');
            const items = Array.from(fileList.children);
            
            items.sort((a, b) => {
                // Always put folders first
                if (a.dataset.type !== b.dataset.type) {
                    return a.dataset.type === 'folder' ? -1 : 1;
                }
                
                let aVal, bVal;
                
                switch (sortBy[pane]) {
                    case 'name':
                        aVal = a.dataset.name.toLowerCase();
                        bVal = b.dataset.name.toLowerCase();
                        break;
                    case 'type':
                        aVal = a.dataset.extension.toLowerCase();
                        bVal = b.dataset.extension.toLowerCase();
                        break;
                    case 'size':
                        aVal = parseInt(a.dataset.size) || 0;
                        bVal = parseInt(b.dataset.size) || 0;
                        break;
                    case 'date':
                        aVal = parseInt(a.dataset.modified) || 0;
                        bVal = parseInt(b.dataset.modified) || 0;
                        break;
                    default:
                        aVal = a.dataset.name.toLowerCase();
                        bVal = b.dataset.name.toLowerCase();
                }
                
                let result;
                if (typeof aVal === 'string') {
                    result = aVal.localeCompare(bVal);
                } else {
                    result = aVal - bVal;
                }
                
                return sortOrder[pane] === 'asc' ? result : -result;
            });
            
            // Re-append sorted items
            items.forEach(item => fileList.appendChild(item));
        }

        function getCurrentPath() {
            const url = new URL(window.location);
            return url.searchParams.get('left') || '';
        }

        // Keyboard shortcuts
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Delete' && selectedFiles.size > 0) {
                    selectedFiles.forEach(path => deleteFile(path));
                } else if (e.key === 'F5') {
                    e.preventDefault();
                    refreshPanes();
                } else if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    document.querySelectorAll('.file-item').forEach(item => {
                        item.classList.add('selected');
                        selectedFiles.add(item.dataset.path);
                    });
                    updateStatus();
                } else if (e.ctrlKey && e.key === 'c' && selectedFiles.size > 0) {
                    clipboard = {
                        path: Array.from(selectedFiles)[0],
                        operation: 'copy'
                    };
                    showNotification('File(s) copied', 'success');
                } else if (e.ctrlKey && e.key === 'x' && selectedFiles.size > 0) {
                    clipboard = {
                        path: Array.from(selectedFiles)[0],
                        operation: 'cut'
                    };
                    showNotification('File(s) cut', 'success');
                } else if (e.ctrlKey && e.key === 'v' && clipboard) {
                    pasteFile(getCurrentPath());
                }
            });
        }

        // Notifications
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Path input enter key support
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('left-path-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    navigateToPath('left');
                }
            });
            
            document.getElementById('right-path-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    navigateToPath('right');
                }
            });
        });
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($file) {
    if ($file['type'] === 'folder') return '📁';
    
    $ext = strtolower($file['extension']);
    $icons = [
        'txt' => '📄', 'md' => '📝', 'rtf' => '📄',
        'html' => '🌐', 'css' => '🎨', 'js' => '⚡', 'ts' => '⚡', 'php' => '🐘',
        'py' => '🐍', 'java' => '☕', 'cpp' => '⚙️', 'c' => '⚙️',
        'json' => '📋', 'xml' => '📋',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'svg' => '🖼️', 'ico' => '🖼️', 'webp' => '🖼️',
        'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'tar' => '📦', 'gz' => '📦',
        'mp4' => '🎬', 'avi' => '🎬', 'mov' => '🎬', 'wmv' => '🎬', 'mkv' => '🎬', 'webm' => '🎬',
        'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵', 'm4a' => '🎵',
        'pdf' => '📕',
        'doc' => '📘', 'docx' => '📘',
        'xls' => '📗', 'xlsx' => '📗',
        'ppt' => '📙', 'pptx' => '📙',
        'ps1' => '💻',
        'ovpn' => '🔒',
        'tmp' => '📄',
        'sys' => '⚙️',
    ];
    
    return $icons[$ext] ?? '📄';
}

function getFileIconClass($file) {
    if ($file['type'] === 'folder') return 'folder';
    
    $ext = strtolower($file['extension']);
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'];
    $codeExts = ['html', 'css', 'js', 'ts', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml', 'ps1'];
    $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];
    
    if (in_array($ext, $imageExts)) return 'image';
    if (in_array($ext, $codeExts)) return 'code';
    if (in_array($ext, $archiveExts)) return 'archive';
    
    return 'file';
}
?>
