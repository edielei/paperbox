<?php
require 'config.php';
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL");
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

        $expireDate = trim($_POST['expire_date'] ?? '') ?: null;
        // 保存当前版本到历史记录
        save_document_version($id, $doc['title'], $doc['category'], $doc['content'], $doc['tags'], $doc['image_path']);
        $stmt = $pdo->prepare("UPDATE documents SET title=?, category=?, content=?, tags=?, expire_date=?, updated_at=datetime('now', '+8 hours') WHERE id=?");
        $stmt->execute([$title, $category, $content, $tags, $expireDate, $id]);
        set_document_images($id, $newPaths);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'url' => 'view.php?id=' . $id]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => $msg]);
        exit;
    }
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
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
        <h1>编辑文档</h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" id="mainForm" autocomplete="off">
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
            <label>到期日期（可选）</label>
            <input type="date" name="expire_date" value="<?= !empty($doc['expire_date']) ? date('Y-m-d', strtotime($doc['expire_date'])) : '' ?>">
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
            <div class="editor-container">
            <div class="editor-tabs">
                <button type="button" class="tab-btn active" id="btnEdit">编辑</button>
                <button type="button" class="tab-btn" id="btnPreview">预览</button>
            </div>
            <div class="md-toolbar" id="mdToolbar">
                <button type="button" class="md-tool-btn" data-action="bold" title="粗体"><b>B</b></button>
                <button type="button" class="md-tool-btn" data-action="italic" title="斜体"><i>I</i></button>
                <button type="button" class="md-tool-btn" data-action="underline" title="下划线"><u>U</u></button>
                <button type="button" class="md-tool-btn" data-action="highlight" title="高亮"><span style="background:#fff3a0;padding:0 3px;">H</span></button>
                <span class="md-tool-sep"></span>
                <div class="md-tool-dropdown">
                    <button type="button" class="md-tool-btn" data-action="heading" title="标题">H ▾</button>
                    <div class="md-tool-menu">
                        <button type="button" data-heading="1">标题 1</button>
                        <button type="button" data-heading="2">标题 2</button>
                        <button type="button" data-heading="3">标题 3</button>
                    </div>
                </div>
                <button type="button" class="md-tool-btn" data-action="quote" title="引用">&ldquo;</button>
                <button type="button" class="md-tool-btn" data-action="code" title="行内代码">&lt;/&gt;</button>
                <button type="button" class="md-tool-btn" data-action="codeblock" title="代码块">{ }</button>
                <span class="md-tool-sep"></span>
                <button type="button" class="md-tool-btn" data-action="ul" title="无序列表">• 列表</button>
                <button type="button" class="md-tool-btn" data-action="ol" title="有序列表">1. 列表</button>
                <button type="button" class="md-tool-btn" data-action="table" title="表格">表格</button>
                <button type="button" class="md-tool-btn" data-action="link" title="链接">链接</button>
                <button type="button" class="md-tool-btn" data-action="image" title="图片">图片</button>
                <span class="md-tool-sep"></span>
                <button type="button" class="md-tool-btn" data-action="sup" title="上标">x²</button>
                <button type="button" class="md-tool-btn" data-action="sub" title="下标">x₂</button>
                <button type="button" class="md-tool-btn" data-action="hr" title="分割线">—</button>
                <span class="md-tool-sep"></span>
                <button type="button" class="md-tool-btn" id="fullscreenBtn" title="全屏编辑">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
                </button>
                <button type="button" class="md-tool-btn" title="Markdown 语法帮助" onclick="window.open('markdown-help.php','_blank')">?</button>
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

<div class="table-modal" id="tableModal" style="display:none;">
    <div class="table-modal-overlay" id="tableModalOverlay"></div>
    <div class="table-modal-box">
        <h3>插入表格</h3>
        <div class="table-modal-row">
            <label>行数</label>
            <input type="number" id="tableRows" value="3" min="1" max="50">
        </div>
        <div class="table-modal-row">
            <label>列数</label>
            <input type="number" id="tableCols" value="3" min="1" max="20">
        </div>
        <div class="table-modal-actions">
            <button type="button" class="btn btn-secondary btn-small" id="tableCancel">取消</button>
            <button type="button" class="btn btn-small" id="tableConfirm">确定</button>
        </div>
    </div>
</div>

<!-- 链接对话框 -->
<div class="table-modal" id="linkModal" style="display:none;">
    <div class="table-modal-overlay" id="linkModalOverlay"></div>
    <div class="table-modal-box">
        <h3>插入链接</h3>
        <div class="table-modal-row">
            <label>网址</label>
            <input type="text" id="linkUrl" placeholder="https://">
        </div>
        <div class="table-modal-row">
            <label>文字</label>
            <input type="text" id="linkText" placeholder="链接文字（可选）">
        </div>
        <div class="table-modal-actions">
            <button type="button" class="btn btn-secondary btn-small" id="linkCancel">取消</button>
            <button type="button" class="btn btn-small" id="linkConfirm">确定</button>
        </div>
    </div>
</div>

<!-- 图片对话框 -->
<div class="table-modal" id="imageModal" style="display:none;">
    <div class="table-modal-overlay" id="imageModalOverlay"></div>
    <div class="table-modal-box">
        <h3>插入图片</h3>
        <div class="table-modal-row">
            <label>网址</label>
            <input type="text" id="imageUrl" placeholder="http://...">
        </div>
        <div class="table-modal-row">
            <label>描述</label>
            <input type="text" id="imageAlt" placeholder="图片描述（可选）">
        </div>
        <div class="table-modal-actions">
            <button type="button" class="btn btn-secondary btn-small" id="imageCancel">取消</button>
            <button type="button" class="btn btn-small" id="imageConfirm">确定</button>
        </div>
    </div>
</div>
    </form>
</div>

<script src="js/editor.js"></script>
<footer class="site-footer">
    <p>PaperBox 纸质文档管理系统 &copy; <?php echo date('Y'); ?></p>
</footer>
</body>
</html>
