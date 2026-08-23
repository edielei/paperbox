<?php
require 'config.php';
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) not_found();

$existingImages = get_document_images($id);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags = trim(str_replace('，', ',', $_POST['tags'] ?? ''));
    $imageOrder = trim($_POST['image_order'] ?? '');
    $newPaths = $imageOrder ? array_filter(explode(',', $imageOrder)) : [];

    if (!$title) {
        $msg = '请填写标题';
    } else {
        // 找出被删除的图片，清理文件
        $removed = array_diff($existingImages, $newPaths);
        foreach ($removed as $p) delete_image_file($p);

        $stmt = $pdo->prepare("UPDATE documents SET title=?, category=?, content=?, tags=?, updated_at=datetime('now', '+8 hours') WHERE id=?");
        $stmt->execute([$title, $category, $content, $tags, $id]);
        set_document_images($id, $newPaths);
        redirect('view.php?id=' . $id);
    }
    $existingImages = $newPaths;
    $doc = array_merge($doc, ['title' => $title, 'category' => $category, 'content' => $content, 'tags' => $tags]);
}

$cats = get_categories();
$existingJson = array_map(function($p) {
    return ['path' => $p, 'url' => UPLOAD_URL . $p];
}, $existingImages);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑文档</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <a href="view.php?id=<?= $id ?>" class="back-btn" title="返回">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            返回
        </a>
        <h1>编辑文档</h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" id="mainForm">
        <?= csrf_field() ?>
        <input type="hidden" name="image_order" id="imageOrder" value="<?= e(implode(',', $existingImages)) ?>">

        <div class="form-card">
        <div class="form-group">
            <label>标题 *</label>
            <input type="text" name="title" value="<?= e($doc['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>分类</label>
            <input type="text" name="category" list="catlist" value="<?= e($doc['category']) ?>">
            <datalist id="catlist">
                <?php foreach ($cats as $c): ?>
                <option value="<?= e($c) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>标签（中英文逗号均可分隔）</label>
            <input type="text" name="tags" value="<?= e($doc['tags']) ?>">
        </div>

        <div class="form-group">
            <label>图片（可多选追加，拖拽排序）</label>
            <input type="file" id="fileInput" accept="image/*" multiple style="display:none">
            <button type="button" class="btn btn-secondary btn-small" id="addImgBtn">+ 追加图片</button>
            <div class="image-list" id="imageList" data-existing='<?= json_encode($existingJson) ?>'></div>
            <div class="hint">支持 JPG / PNG / GIF / WEBP 格式，单张最大 20MB；拖拽图片可调整顺序，点 × 删除</div>
        </div>

        <div class="form-group">
            <label>详细内容</label>
            <div class="editor-tabs">
                <button type="button" class="tab-btn active" id="btnEdit">编辑</button>
                <button type="button" class="tab-btn" id="btnPreview">预览</button>
            </div>
            <textarea name="content" id="contentInput" class="editor-textarea"><?= e($doc['content']) ?></textarea>
            <div class="preview-pane" id="previewPane" style="display:none;"></div>
            <div class="md-hint">
                支持 Markdown 语法：
                <code># 标题</code> <code>**粗体**</code> <code>*斜体*</code>
                <code>`代码`</code> <code>- 列表</code> <code>[链接](url)</code> <code>|表格|</code>
                <a href="markdown-help.php" target="_blank" class="md-help-link">语法帮助 →</a>
            </div>
        </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn">保存修改</button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div>

<script src="js/editor.js"></script>
</body>
</html>
