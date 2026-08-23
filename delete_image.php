<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
verify_csrf();

$path = $_POST['path'] ?? '';
// 安全校验：防止路径遍历，只允许删除 uploads/ 下的文件
$path = basename($path);
if ($path === '' || $path === '.' || $path === '..') {
    echo json_encode(['ok' => false, 'error' => '无效的路径']);
    exit;
}

$fullPath = UPLOAD_DIR . $path;
if (file_exists($fullPath) && is_file($fullPath)) {
    if (@unlink($fullPath)) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => '删除失败']);
    }
} else {
    echo json_encode(['ok' => true, 'msg' => '文件不存在']);
}
