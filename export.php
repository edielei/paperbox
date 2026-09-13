<?php
require 'config.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('服务器未启用 ZipArchive 扩展');
}

$imagesOnly = isset($_GET['images']) ? true : false;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$tags = trim($_GET['tags'] ?? '');
$search = trim($_GET['q'] ?? '');
$countOnly = isset($_GET['count']) ? true : false;

// 收集要导出的图片路径
$imagePaths = [];

if ($startDate || $endDate || $tags || $search) {
    // 按条件筛选文档
    $where = ["deleted_at IS NULL"];
    $params = [];
    if ($startDate) {
        $where[] = "created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $where[] = "created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    if ($tags) {
        $tagList = array_filter(explode(',', $tags));
        $tagConds = [];
        foreach ($tagList as $t) {
            $tagConds[] = "tags LIKE ?";
            $params[] = '%' . $t . '%';
        }
        if ($tagConds) {
            $where[] = '(' . implode(' OR ', $tagConds) . ')';
        }
    }
    if ($search) {
        $where[] = "(title LIKE ? OR content LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = "SELECT id FROM documents WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($docIds as $did) {
        $imgs = get_document_images($did);
        $imagePaths = array_merge($imagePaths, $imgs);
    }
    $imagePaths = array_unique($imagePaths);
} else {
    // 全部图片
    if (is_dir(UPLOAD_DIR)) {
        $files = scandir(UPLOAD_DIR);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === 'index.html') continue;
            if (is_file(UPLOAD_DIR . $f)) {
                $imagePaths[] = $f;
            }
        }
    }
}

// 只返回数量
if ($countOnly) {
    header('Content-Type: application/json');
    echo json_encode(['count' => count($docIds ?? []), 'images' => count($imagePaths)]);
    exit;
}

$zip = new ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'paperbox_export_');
if ($zip->open($tempFile, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    die('创建ZIP失败');
}

// 添加数据库（仅整站备份时）
if (!$imagesOnly && !$startDate && !$endDate && !$tags && !$search) {
    if (file_exists(DB_FILE)) {
        $zip->addFile(DB_FILE, 'paper.db');
    }
}

// 添加图片
foreach ($imagePaths as $p) {
    $filePath = UPLOAD_DIR . $p;
    if (file_exists($filePath) && is_file($filePath)) {
        $zip->addFile($filePath, 'uploads/' . $p);
    }
}

$zip->close();

// 生成文件名
if ($startDate || $endDate) {
    $suffix = 'date_' . ($startDate ?: 'all') . '_' . ($endDate ?: 'all');
} elseif ($tags) {
    $suffix = 'tags_' . substr(md5($tags), 0, 8);
} elseif ($search) {
    $suffix = 'search_' . substr(md5($search), 0, 8);
} elseif ($imagesOnly) {
    $suffix = 'images';
} else {
    $suffix = 'full';
}

$filename = 'paperbox_' . $suffix . '_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: no-cache, must-revalidate');
readfile($tempFile);
@unlink($tempFile);
