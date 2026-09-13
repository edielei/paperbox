<?php
require 'config.php';
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) not_found();

// 处理回滚
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback']) && isset($_POST['version_id'])) {
    verify_csrf();
    $versionId = intval($_POST['version_id']);
    if (rollback_document($id, $versionId)) {
        header('Location: view.php?id=' . $id);
        exit;
    } else {
        $error = '回滚失败，版本不存在';
    }
}

$versions = get_document_versions($id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>版本历史 - <?= e($doc['title']) ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <a href="view.php?id=<?= $id ?>" class="back-btn" title="返回">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
        <h1>版本历史</h1>
    </div>

    <?php if (isset($error)): ?>
    <div class="settings-saved" style="background:#fef0f0;color:#f56c6c;"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div style="margin-bottom:16px;">
                <div style="font-size:0.95rem;font-weight:600;color:#303133;"><?= e($doc['title']) ?></div>
                <div style="font-size:0.8rem;color:#909399;margin-top:4px;">共 <?= count($versions) ?> 个历史版本（最多保留 10 个）</div>
            </div>

            <?php if (empty($versions)): ?>
            <div style="text-align:center;padding:40px 0;color:#909399;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;opacity:0.5;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <div>暂无历史版本</div>
                <div style="font-size:0.8rem;margin-top:4px;">编辑并保存文档后，将自动记录版本历史</div>
            </div>
            <?php else: ?>
            <div class="version-list">
                <?php foreach ($versions as $idx => $v): ?>
                <div class="version-item">
                    <div class="version-info">
                        <div class="version-title">
                            <span class="version-badge">V<?= count($versions) - $idx ?></span>
                            <span class="version-doc-title"><?= e($v['title'] ?: '（无标题）') ?></span>
                        </div>
                        <div class="version-meta">
                            <span><?= date('Y-m-d H:i', strtotime($v['created_at'])) ?></span>
                            <?php if ($v['category']): ?><span class="version-cat"><?= e($v['category']) ?></span><?php endif; ?>
                            <?php if ($v['tags']): ?><span class="version-tags"><?= e($v['tags']) ?></span><?php endif; ?>
                            <span class="version-length"><?= $v['content_length'] ?> 字</span>
                        </div>
                    </div>
                    <div class="version-actions">
                        <button type="button" class="btn btn-secondary btn-small" onclick="showVersion(<?= $v['id'] ?>)">查看</button>
                        <form method="post" action="" style="display:inline;" onsubmit="return confirm('确定回滚到此版本吗？当前内容将保存为新版本。')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="rollback" value="1">
                            <input type="hidden" name="version_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn btn-small">回滚</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 版本查看弹窗 -->
<div class="table-modal" id="versionModal" style="display:none;">
    <div class="table-modal-overlay" onclick="closeVersionModal()"></div>
    <div class="table-modal-box" style="max-width:600px;">
        <h3 id="versionModalTitle">版本详情</h3>
        <div id="versionModalContent" style="max-height:400px;overflow-y:auto;font-size:0.85rem;line-height:1.7;color:#606266;white-space:pre-wrap;word-break:break-word;"></div>
        <div class="table-modal-actions">
            <button type="button" class="btn btn-secondary btn-small" onclick="closeVersionModal()">关闭</button>
        </div>
    </div>
</div>

<footer class="site-footer">
    <p>PaperBox 纸质文档管理系统 &copy; <?= date('Y') ?></p>
</footer>

<script>
function showVersion(versionId) {
    fetch('version_content.php?id=' + versionId)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('versionModalTitle').textContent = data.title + '（' + data.created_at + '）';
                document.getElementById('versionModalContent').textContent = data.content || '（无内容）';
                document.getElementById('versionModal').style.display = 'flex';
            } else {
                alert('加载失败');
            }
        })
        .catch(() => alert('加载失败'));
}
function closeVersionModal() {
    document.getElementById('versionModal').style.display = 'none';
}
document.getElementById('versionModal').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVersionModal();
});
</script>
</body>
</html>
