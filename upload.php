<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
verify_csrf();

$result = upload_image($_FILES['file'] ?? []);
if ($result['error']) {
    echo json_encode(['ok' => false, 'error' => $result['error']]);
} else {
    echo json_encode(['ok' => true, 'path' => $result['path'], 'url' => UPLOAD_URL . $result['path']]);
}
