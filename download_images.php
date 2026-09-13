<?php
require 'config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('无效的文档ID');
}

$stmt = $pdo->prepare("SELECT title FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) {
    http_response_code(404);
    die('文档不存在');
}

$images = get_document_images($id);
if (empty($images)) {
    http_response_code(404);
    die('该文档没有图片');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('服务器未启用 ZipArchive 扩展');
}

$zip = new ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'paperbox_');
if ($zip->open($tempFile, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    die('创建ZIP失败');
}

$count = 0;
foreach ($images as $img) {
    $filePath = UPLOAD_DIR . $img;
    if (file_exists($filePath) && is_file($filePath)) {
        $zip->addFile($filePath, $img);
        $count++;
    }
}
$zip->close();

if ($count === 0) {
    @unlink($tempFile);
    http_response_code(404);
    die('图片文件不存在');
}

$rawName = $doc['title'] ? $doc['title'] : 'document_' . $id;
$safeName = preg_replace('/[\/\\\?%\*:|"<>]/', '_', $rawName);
$zipName = $safeName . '_' . $count . '张图片.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"; filename*=UTF-8\'\'' . rawurlencode($zipName));
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: no-cache, must-revalidate');
readfile($tempFile);
@unlink($tempFile);
