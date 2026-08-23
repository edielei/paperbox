<?php
// config.php — 全局配置与公共函数

session_start();
date_default_timezone_set('Asia/Shanghai');

define('DB_FILE', __DIR__ . '/paper.db');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 20 * 1024 * 1024);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 初始化数据库
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA synchronous = NORMAL");
    $pdo->exec("PRAGMA cache_size = -20000");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            category TEXT DEFAULT '',
            content TEXT DEFAULT '',
            image_path TEXT DEFAULT '',
            tags TEXT DEFAULT '',
            created_at DATETIME DEFAULT (datetime('now', '+8 hours')),
            updated_at DATETIME DEFAULT (datetime('now', '+8 hours'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_category ON documents(category)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_updated_at ON documents(updated_at DESC)");

    // 多图表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_doc_images ON document_images(document_id, sort_order)");

    // 旧数据迁移：把 documents.image_path 迁移到 document_images
    $cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll();
    $hasOldField = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'image_path') { $hasOldField = true; break; }
    }
    if ($hasOldField) {
        $oldDocs = $pdo->query("SELECT id, image_path FROM documents WHERE image_path IS NOT NULL AND image_path != ''")->fetchAll();
        foreach ($oldDocs as $od) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM document_images WHERE document_id = ?");
            $cnt->execute([$od['id']]);
            if ($cnt->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO document_images (document_id, image_path, sort_order) VALUES (?, ?, 0)")
                    ->execute([$od['id'], $od['image_path']]);
            }
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败，请检查 paper.db 文件权限。');
}

require __DIR__ . '/markdown.php';

// ---------- 辅助函数 ----------

function e($str) {
    $str = (string)($str ?? '');
    if ($str === '') return '';
    // 仅在不是有效 UTF-8 时才尝试转换，避免对正常数据误判
    if (function_exists('mb_check_encoding') && !mb_check_encoding($str, 'UTF-8')) {
        if (function_exists('mb_convert_encoding')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5,ASCII');
        }
    }
    $result = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $result !== '' ? $result : $str;
}

function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function not_found($msg = '文档不存在') {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>未找到</title><link rel="icon" href="favicon.ico" type="image/x-icon"><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="css/style.css"></head><body>';
    echo '<div class="container"><div class="empty">';
    echo '<div class="empty-icon">🔍</div><p>' . e($msg) . '</p>';
    echo '<a href="index.php" class="btn" style="margin-top:15px;">返回列表</a>';
    echo '</div></div></body></html>';
    exit;
}

function get_categories() {
    global $pdo;
    return $pdo->query("SELECT DISTINCT category FROM documents WHERE category != '' ORDER BY category")
               ->fetchAll(PDO::FETCH_COLUMN);
}

function get_all_tags() {
    global $pdo;
    $rows = $pdo->query("SELECT tags FROM documents WHERE tags != ''")->fetchAll(PDO::FETCH_COLUMN);
    $all = [];
    foreach ($rows as $tags) {
        foreach (preg_split('/[,，]/u', $tags) as $t) {
            $t = trim($t);
            if ($t !== '') $all[$t] = true;
        }
    }
    ksort($all);
    return array_keys($all);
}

// ---------- CSRF ----------

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('请求验证失败，请刷新页面重试。');
    }
}

// ---------- 图片上传 ----------

function upload_image($file) {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => ''];
    }
    if ($file['size'] > MAX_IMAGE_SIZE) {
        return ['path' => '', 'error' => '图片不能超过 20MB'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        return ['path' => '', 'error' => '不支持的图片格式（仅支持 jpg/png/gif/webp）'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mime, true)) {
        return ['path' => '', 'error' => '文件不是有效的图片'];
    }
    $newName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
        return ['path' => '', 'error' => '图片上传失败，请检查 uploads 目录权限'];
    }
    return ['path' => $newName, 'error' => ''];
}

function delete_image_file($imagePath) {
    if ($imagePath && file_exists(UPLOAD_DIR . $imagePath)) {
        @unlink(UPLOAD_DIR . $imagePath);
    }
}

// 处理多图上传，返回 [路径数组, 错误信息]
function upload_multiple_images($files) {
    $paths = [];
    if (empty($files['tmp_name']) || !is_array($files['tmp_name'])) {
        return [$paths, ''];
    }
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
        $result = upload_image($file);
        if ($result['error']) return [[], $result['error']];
        if ($result['path']) $paths[] = $result['path'];
    }
    return [$paths, ''];
}

// ---------- 文档图片管理 ----------

function get_document_images($doc_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT image_path FROM document_images WHERE document_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$doc_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_first_image($doc_id) {
    $images = get_document_images($doc_id);
    return $images[0] ?? '';
}

// 批量获取多个文档的首张图，返回 [doc_id => image_path]
function get_first_images_batch($docIds) {
    global $pdo;
    if (empty($docIds)) return [];
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $sql = "SELECT document_id, image_path FROM document_images
            WHERE document_id IN ($placeholders)
            ORDER BY document_id ASC, sort_order ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($docIds);
    $result = [];
    while ($row = $stmt->fetch()) {
        if (!isset($result[$row['document_id']])) {
            $result[$row['document_id']] = $row['image_path'];
        }
    }
    return $result;
}

function set_document_images($doc_id, $paths) {
    global $pdo;
    $pdo->prepare("DELETE FROM document_images WHERE document_id = ?")->execute([$doc_id]);
    $stmt = $pdo->prepare("INSERT INTO document_images (document_id, image_path, sort_order) VALUES (?, ?, ?)");
    foreach ($paths as $i => $path) {
        if ($path) $stmt->execute([$doc_id, $path, $i]);
    }
}

function delete_document_images_files($doc_id) {
    foreach (get_document_images($doc_id) as $path) {
        delete_image_file($path);
    }
}

// ---------- 列表摘要 ----------

function plain_summary($text, $len = 100) {
    $text = $text ?? '';
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    $text = preg_replace('/^#{1,6}\s+/m', '', $text);
    $text = preg_replace('/^[-*+]\s+/m', '', $text);
    $text = preg_replace('/^\d+\.\s+/m', '', $text);
    $text = preg_replace('/^>\s?/m', '', $text);
    $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
    $text = preg_replace('/[*_~`]/', '', $text);
    $text = preg_replace('/\s+/', ' ', trim($text));
    return mb_substr($text, 0, $len);
}

// 搜索关键词高亮（在已转义的文本上使用）
function highlight($text, $keyword) {
    if ($text === '') return $text;
    $keywords = is_array($keyword) ? $keyword : [$keyword];
    $keywords = array_filter($keywords, 'strlen');
    if (empty($keywords)) return $text;
    foreach ($keywords as $kw) {
        $pattern = '/(' . preg_quote($kw, '/') . ')/iu';
        $result = @preg_replace($pattern, '<mark class="hl">$1</mark>', $text);
        $text = $result ?? $text;
    }
    return $text;
}
