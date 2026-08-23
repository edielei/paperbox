<?php
require 'config.php';
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) not_found();

$images = get_document_images($id);
$imageUrls = array_map(function($p) { return UPLOAD_URL . $p; }, $images);
$md = new SimpleMD();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($doc['title']) ?></title>
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
        <h1>文档详情</h1>
        <div class="more-wrap">
            <button class="more-btn" id="moreBtn" onclick="toggleMore(event)" aria-label="更多操作">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            </button>
            <div class="more-dropdown" id="moreDropdown">
                <a href="edit.php?id=<?= $id ?>" class="more-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    编辑
                </a>
                <form method="post" action="delete.php" class="more-item-form" onsubmit="return confirm('确定删除？所有图片也会一起删除！')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="more-item more-danger">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        删除
                    </button>
                </form>
                <div class="more-divider"></div>
                <a href="index.php" class="more-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    返回首页
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($images)): ?>
    <?php $imgCount = count($images); ?>
    <div class="detail-gallery gallery-<?= min($imgCount, 6) ?>" id="imgGallery" data-images='<?= json_encode($imageUrls) ?>'>
        <?php foreach ($images as $idx => $path): ?>
        <?php $url = UPLOAD_URL . $path; ?>
        <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="gallery-item gallery-link<?= $idx >= 6 ? ' hidden' : '' ?>" data-idx="<?= $idx ?>">
            <img src="<?= e($url) ?>" alt="" loading="lazy">
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($imgCount > 6): ?>
    <button class="load-more-btn" onclick="showAllImages()">展开全部 <?= $imgCount ?> 张图片</button>
    <?php endif; ?>
    <?php endif; ?>

    <div class="detail-content">
        <h2><?= e($doc['title']) ?></h2>
        <div class="detail-meta">
            <?php if ($doc['category']): ?>
            <span class="meta-cat">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <?= e($doc['category']) ?>
            </span>
            <?php endif; ?>
            <span class="meta-time">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                更新于 <?= formatDate($doc['updated_at']) ?>
            </span>
        </div>

        <?php if ($doc['tags']): ?>
        <div class="detail-tags">
            <span class="tags-label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                标签
            </span>
            <div class="tags-list">
                <?php foreach (preg_split('/[,，]/u', $doc['tags']) as $t): ?>
                <?php $t = trim($t); if ($t === '') continue; ?>
                <a href="index.php?tags=<?= urlencode($t) ?>" class="tag" title="筛选标签：<?= e($t) ?>"><?= e($t) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="detail-divider"></div>

        <div class="markdown-body">
            <?php if ($doc['content']): ?>
                <?= $md->text($doc['content']) ?>
            <?php else: ?>
                <p style="color:#999;">（无文字内容）</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 图片弹窗（仅桌面端使用） -->
<div class="modal-overlay" id="imgModal">
    <div class="modal-close" onclick="closeModal()"></div>
    <?php if (count($images) > 1): ?>
    <button class="modal-nav modal-prev" onclick="prevImage(event)">&#10094;</button>
    <button class="modal-nav modal-next" onclick="nextImage(event)">&#10095;</button>
    <?php endif; ?>
    <img src="" class="modal-img" id="modalImg" alt="">
    <div class="modal-hint" id="modalCounter"></div>
</div>

<script src="js/common.js"></script>
<script src="js/viewer.js"></script>

</body>
</html>
