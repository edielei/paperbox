<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

verify_csrf();

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) redirect('index.php');

// 先删除图片文件，再删数据库记录（外键级联删 document_images）
delete_document_images_files($id);
$pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);

redirect('index.php');
