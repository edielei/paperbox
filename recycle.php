<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'restore') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE documents SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        }
    } elseif ($action === 'purge') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            delete_document_images_files($id);
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
        }
    } elseif ($action === 'empty') {
        $ids = $pdo->query("SELECT id FROM documents WHERE deleted_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            delete_document_images_files($id);
        }
        $pdo->exec("DELETE FROM documents WHERE deleted_at IS NOT NULL");
    }
    redirect('recycle.php');
}

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NOT NULL")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM documents WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$docs = $stmt->fetchAll();

$docIds = array_column($docs, 'id');
$firstImages = get_first_images_batch($docIds);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>回收站 - PaperBox</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <a href="index.php" class="back-btn" title="返回">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </a>
        <h1>回收站</h1>
    </div>

    <?php if ($total > 0): ?>
    <div class="recycle-toolbar">
        <span>共 <?= $total ?> 个文档</span>
        <form method="post" class="inline-form" onsubmit="return confirm('确定清空回收站？所有文档将彻底删除，无法恢复！');">
            <input type="hidden" name="action" value="empty">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-small">清空回收站</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (empty($docs)): ?>
    <div class="empty">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        <p>回收站是空的</p>
        <a href="index.php" class="btn">返回首页</a>
    </div>
    <?php else: ?>
    <div class="doc-list">
        <?php foreach ($docs as $doc): ?>
        <div class="card recycle-card">
            <?php if (!empty($firstImages[$doc['id']])): ?>
            <img src="<?= e(UPLOAD_URL . $firstImages[$doc['id']]) ?>" class="card-img" alt="" loading="lazy">
            <?php else: ?>
            <div class="card-img card-img-placeholder">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            </div>
            <?php endif; ?>
            <div class="card-body">
                <h3 class="card-title"><?= e($doc['title']) ?></h3>
                <div class="card-meta">
                    <?php if ($doc['category']): ?>
                    <span class="card-cat">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        <?= e($doc['category']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="card-time">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        删除于 <?= formatDate($doc['deleted_at']) ?>
                    </span>
                </div>
                <div class="recycle-actions">
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-small">恢复</button>
                    </form>
                    <form method="post" class="inline-form" onsubmit="return confirm('确定彻底删除？该操作不可恢复！');">
                        <input type="hidden" name="action" value="purge">
                        <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-small">彻底删除</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="recycle.php?page=1" class="page-btn">首页</a>
        <a href="recycle.php?page=<?= $page - 1 ?>" class="page-btn">上一页</a>
        <?php endif; ?>
        <span class="page-info">第 <?= $page ?> / <?= $totalPages ?> 页</span>
        <?php if ($page < $totalPages): ?>
        <a href="recycle.php?page=<?= $page + 1 ?>" class="page-btn">下一页</a>
        <a href="recycle.php?page=<?= $totalPages ?>" class="page-btn">末页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script src="js/common.js"></script>
<footer class="site-footer">
    <p>PaperBox 纸质文档管理系统 &copy; <?php echo date('Y'); ?></p>
</footer>
</body>
</html>
