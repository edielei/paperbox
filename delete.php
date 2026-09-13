<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

verify_csrf();

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) redirect('index.php');

// 软删除：移入回收站，不删除图片文件
$pdo->prepare("UPDATE documents SET deleted_at = datetime('now', '+8 hours') WHERE id = ?")->execute([$id]);

redirect('index.php');
