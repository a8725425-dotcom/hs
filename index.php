<?php
// =============================================
// CacheBridge v2.3 - Distributed Data Relay
// =============================================
// Author: Swill Networks
// Purpose: Temporary data caching with auto-purge
// =============================================

define('STORAGE_PATH', sys_get_temp_dir() . '/cb_cache_');
define('MAX_AGE_SECONDS', 300); // 5 minutes auto-delete
define('PULL_ENDPOINT_SECRET', 'XyZ_2025_SwillBridge_9kLmN');

// Clean old cache entries
function cleanCache() {
    foreach (glob(STORAGE_PATH . '*') as $file) {
        if (time() - filemtime($file) > MAX_AGE_SECONDS) {
            @unlink($file);
        }
    }
}

// Store incoming data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_PAYLOAD_TYPE'])) {
    $payloadType = $_SERVER['HTTP_X_PAYLOAD_TYPE'];
    $rawData = file_get_contents('php://input');
    
    if (strlen($rawData) > 1024) { // Valid data check
        $cacheId = uniqid() . '_' . md5($payloadType . time());
        $cacheFile = STORAGE_PATH . $cacheId . '.dat';
        file_put_contents($cacheFile, $rawData);
        
        // Store metadata
        $metaFile = STORAGE_PATH . $cacheId . '.meta';
        $metadata = json_encode([
            'type' => $payloadType,
            'size' => strlen($rawData),
            'time' => time(),
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        file_put_contents($metaFile, $metadata);
        
        echo "QUEUED:" . $cacheId;
    }
    exit;
}

// PULL endpoint - client connects here to retrieve data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['pull']) && $_GET['pull'] === PULL_ENDPOINT_SECRET) {
    cleanCache();
    
    $allCache = glob(STORAGE_PATH . '*.dat');
    $results = [];
    
    foreach ($allCache as $cacheFile) {
        $metaFile = str_replace('.dat', '.meta', $cacheFile);
        if (file_exists($metaFile)) {
            $metadata = json_decode(file_get_contents($metaFile), true);
            $data = base64_encode(file_get_contents($cacheFile));
            $results[] = [
                'meta' => $metadata,
                'data' => $data
            ];
            // Don't delete yet - client confirms
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'items' => $results]);
    exit;
}

// Confirmation endpoint - client says "I got it, delete"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['confirm']) && $_GET['confirm'] === PULL_ENDPOINT_SECRET) {
    $input = json_decode(file_get_contents('php://input'), true);
    $confirmedIds = $input['ids'] ?? [];
    
    foreach ($confirmedIds as $id) {
        $datFile = STORAGE_PATH . $id . '.dat';
        $metaFile = STORAGE_PATH . $id . '.meta';
        @unlink($datFile);
        @unlink($metaFile);
    }
    
    echo "CLEANED:" . count($confirmedIds);
    exit;
}

// Default response
echo "CacheBridge operational | " . date('Y-m-d H:i:s');
?>
