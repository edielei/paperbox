<?php
require 'config.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
$version = get_document_version($id);

if (!$version) {
    echo json_encode(['ok' => false, 'msg' => '版本不存在']);
    exit;
}

echo json_encode([
    'ok' => true,
    'title' => $version['title'] ?: '（无标题）',
    'content' => $version['content'],
    'category' => $version['category'],
    'tags' => $version['tags'],
    'created_at' => date('Y-m-d H:i', strtotime($version['created_at']))
]);
