<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

define('DB_FILE', __DIR__ . '/paper.db');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_IMAGE_SIZE', 20 * 1024 * 1024);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            title TEXT DEFAULT '',
            category TEXT DEFAULT '',
            content TEXT DEFAULT '',
            tags TEXT DEFAULT '',
            image_path TEXT DEFAULT '',
            created_at DATETIME DEFAULT (datetime('now', '+8 hours')),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_versions_doc ON document_versions(document_id, created_at DESC)");

    // 迁移：增加字段
    $cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('deleted_at', $cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN deleted_at DATETIME DEFAULT NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_deleted_at ON documents(deleted_at)");
    }
    if (!in_array('expire_date', $cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN expire_date DATETIME DEFAULT NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expire_date ON documents(expire_date)");
    }
    if (!in_array('is_starred', $cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN is_starred INTEGER DEFAULT 0");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_starred ON documents(is_starred)");
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败，请检查 paper.db 文件权限。');
}

require __DIR__ . '/markdown.php';

function e($str) {
    $str = (string)($str ?? '');
    if ($str === '') return '';
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
    echo '<div class="empty-icon" style="font-size:3rem;">404</div><p>' . e($msg) . '</p>';
    echo '<a href="index.php" class="btn" style="margin-top:15px;">返回列表</a>';
    echo '</div></div></body></html>';
    exit;
}

function get_categories() {
    global $pdo;
    return $pdo->query("SELECT DISTINCT category FROM documents WHERE deleted_at IS NULL AND category != '' ORDER BY category")
               ->fetchAll(PDO::FETCH_COLUMN);
}

function get_all_tags() {
    global $pdo;
    $rows = $pdo->query("SELECT tags FROM documents WHERE deleted_at IS NULL AND tags != ''")->fetchAll(PDO::FETCH_COLUMN);
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
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
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

function get_document_images($doc_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT image_path FROM document_images WHERE document_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$doc_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

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

function get_setting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? $default : $val;
}

function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$key, (string)$value]);
}

function get_stats() {
    global $pdo;
    $total = $pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL")->fetchColumn();
    $recent = $pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL AND created_at >= datetime('now', '+8 hours', '-7 days')")->fetchColumn();
    $recycle = $pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NOT NULL")->fetchColumn();
    return [
        'total' => $total,
        'recent' => $recent,
        'recycle' => $recycle,
    ];
}

// 保存文档版本历史（最多保留10条）
function save_document_version($docId, $title, $category, $content, $tags, $imagePath) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO document_versions (document_id, title, category, content, tags, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$docId, $title, $category, $content, $tags, $imagePath]);
    // 删除超过10条的旧版本
    $keepIds = $pdo->query("SELECT id FROM document_versions WHERE document_id = " . (int)$docId . " ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    if (count($keepIds) >= 10) {
        $in = implode(',', array_map('intval', $keepIds));
        $pdo->exec("DELETE FROM document_versions WHERE document_id = " . (int)$docId . " AND id NOT IN ($in)");
    }
}

// 获取文档的版本历史列表
function get_document_versions($docId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, category, tags, created_at, length(content) as content_length FROM document_versions WHERE document_id = ? ORDER BY created_at DESC");
    $stmt->execute([$docId]);
    return $stmt->fetchAll();
}

// 获取单个版本详情
function get_document_version($versionId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM document_versions WHERE id = ?");
    $stmt->execute([$versionId]);
    return $stmt->fetch();
}

// 回滚到指定版本
function rollback_document($docId, $versionId) {
    global $pdo;
    $version = get_document_version($versionId);
    if (!$version || $version['document_id'] != $docId) return false;
    // 先保存当前版本到历史
    $current = $pdo->prepare("SELECT title, category, content, tags, image_path FROM documents WHERE id = ?");
    $current->execute([$docId]);
    $cur = $current->fetch();
    if ($cur) {
        save_document_version($docId, $cur['title'], $cur['category'], $cur['content'], $cur['tags'], $cur['image_path']);
    }
    // 回滚
    $stmt = $pdo->prepare("UPDATE documents SET title = ?, category = ?, content = ?, tags = ?, image_path = ?, updated_at = datetime('now', '+8 hours') WHERE id = ?");
    $stmt->execute([$version['title'], $version['category'], $version['content'], $version['tags'], $version['image_path'], $docId]);
    return true;
}
