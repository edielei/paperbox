<?php
require 'config.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags = trim(str_replace('，', ',', $_POST['tags'] ?? ''));
    $imageOrder = trim($_POST['image_order'] ?? '');
    $imagePaths = $imageOrder ? array_filter(explode(',', $imageOrder)) : [];

    if (!$title) {
        $msg = '请填写标题';
    } elseif (empty($imagePaths)) {
        $msg = '请至少上传一张图片';
    } else {
        $stmt = $pdo->prepare("INSERT INTO documents (title, category, content, tags) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $category, $content, $tags]);
        $newId = $pdo->lastInsertId();
        set_document_images($newId, $imagePaths);
        redirect('index.php');
    }
}

$cats = get_categories();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加文档</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <a href="index.php" class="back-btn" title="返回">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            返回
        </a>
        <h1>添加文档</h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" id="mainForm">
        <?= csrf_field() ?>
        <input type="hidden" name="image_order" id="imageOrder" value="">

        <div class="form-card">
        <div class="form-group">
            <label>标题 *</label>
            <input type="text" name="title" required placeholder="例如：水电费票据" value="<?= e($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>分类</label>
            <input type="text" name="category" id="categoryInput" list="catlist" placeholder="例如：票据、说明书、合同、证件" value="<?= e($_POST['category'] ?? '') ?>">
            <datalist id="catlist">
                <?php foreach ($cats as $c): ?>
                <option value="<?= e($c) ?>">
                <?php endforeach; ?>
            </datalist>
            <?php if (!empty($_GET['category'])): ?>
            <div class="cat-suggest" id="catSuggest">
                首页当前分类：<span class="cat-suggest-text"><?= e($_GET['category']) ?></span>
                <button type="button" class="cat-suggest-btn">点击填入</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>标签（用逗号分隔）</label>
            <input type="text" name="tags" placeholder="标签（中英文逗号均可分隔）" value="<?= e($_POST['tags'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>图片 *（可多选，拖拽排序）</label>
            <input type="file" id="fileInput" accept="image/*" multiple style="display:none">
            <button type="button" class="btn btn-secondary btn-small" id="addImgBtn">+ 选择图片</button>
            <div class="image-list" id="imageList"></div>
            <div class="hint">支持 JPG / PNG / GIF / WEBP 格式，单张最大 20MB；拖拽图片可调整顺序</div>
        </div>

        <div class="form-group">
            <label>详细内容</label>
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
                <button type="button" class="md-tool-btn" title="Markdown 语法帮助" onclick="window.open('markdown-help.php','_blank')">?</button>
            </div>
            <textarea name="content" id="contentInput" class="editor-textarea" placeholder="把纸上的关键信息打字录入，方便搜索..."><?= e($_POST['content'] ?? '') ?></textarea>
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
            <button type="submit" class="btn">保存</button>
            <a href="index.php" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div>

<script src="js/editor.js"></script>
</body>
</html>
