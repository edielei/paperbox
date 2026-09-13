<?php
require 'config.php';

$where = ['deleted_at IS NULL'];
$params = [];
$search = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$perPage = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'created_at';
if (!in_array($sort, ['created_at', 'updated_at'])) $sort = 'created_at';
$order = $_GET['order'] ?? 'desc';
if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
$orderSql = strtoupper($order);

$selectedTags = [];
if (isset($_GET['tags']) && $_GET['tags'] !== '') {
    $selectedTags = array_filter(array_map('trim', preg_split('/[,，]/u', $_GET['tags'])));
} elseif (isset($_GET['tag']) && $_GET['tag'] !== '') {
    $selectedTags = [trim($_GET['tag'])];
}
$selectedTags = array_unique($selectedTags);
$expireDays = (int)get_setting('expire_days', 30);

if ($search !== '') {
    $where[] = "(title LIKE :q OR content LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($category !== '') {
    $where[] = "category = :cat";
    $params[':cat'] = $category;
}
if (!empty($selectedTags)) {
    $tagConds = [];
    foreach ($selectedTags as $i => $t) {
        $tagConds[] = "tags LIKE :tag_$i";
        $params[":tag_$i"] = '%' . $t . '%';
    }
    $where[] = '(' . implode(' OR ', $tagConds) . ')';
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM documents" . $whereSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM documents" . $whereSql . " ORDER BY $sort $orderSql LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll();

$docIds = array_column($docs, 'id');
$firstImages = get_first_images_batch($docIds);

$cats = get_categories();
$allTags = get_all_tags();
$hasFilter = ($search !== '' || $category !== '' || !empty($selectedTags));
$stats = get_stats();
$expiringDocs = $pdo->query("SELECT id, title, expire_date FROM documents WHERE deleted_at IS NULL AND expire_date IS NOT NULL AND expire_date <= datetime('now', '+8 hours', '+" . $expireDays . " days') ORDER BY expire_date ASC")->fetchAll();

function page_url($p) {
    $q = $_GET;
    $q['page'] = $p;
    if (empty($q['sort'])) $q['sort'] = 'created_at';
    if (empty($q['order'])) $q['order'] = 'desc';
    return '?' . http_build_query($q);
}
function tag_toggle_url($t) {
    $q = $_GET;
    $current = [];
    if (isset($q['tags']) && $q['tags'] !== '') {
        $current = array_filter(array_map('trim', preg_split('/[,，]/u', $q['tags'])));
    } elseif (isset($q['tag']) && $q['tag'] !== '') {
        $current = [trim($q['tag'])];
    }
    $current = array_unique($current);
    $key = array_search($t, $current);
    if ($key !== false) {
        unset($current[$key]);
    } else {
        $current[] = $t;
    }
    $current = array_values($current);
    unset($q['tag'], $q['page']);
    if (!empty($current)) {
        $q['tags'] = implode(',', $current);
    } else {
        unset($q['tags']);
    }
    if (empty($q['sort'])) $q['sort'] = 'created_at';
    if (empty($q['order'])) $q['order'] = 'desc';
    return $q ? '?' . http_build_query($q) : 'index.php';
}
function clear_all_tags_url() {
    $q = $_GET;
    unset($q['tag'], $q['tags'], $q['page']);
    if (empty($q['sort'])) $q['sort'] = 'created_at';
    if (empty($q['order'])) $q['order'] = 'desc';
    return $q ? '?' . http_build_query($q) : 'index.php';
}
function clear_search_url() {
    $q = $_GET;
    unset($q['q'], $q['page']);
    if (empty($q['sort'])) $q['sort'] = 'created_at';
    if (empty($q['order'])) $q['order'] = 'desc';
    return $q ? '?' . http_build_query($q) : 'index.php';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>纸质文档管理</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<div class="container">
    <div class="header home-header">
        <h1><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2a80eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-right:8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>纸质文档管理</h1>
        <a href="settings.php" class="btn btn-icon-only" title="设置">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>
        <a href="add.php<?= $category ? '?category=' . urlencode($category) : '' ?>" class="btn">
            <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            添加
        </a>
    </div>

    <?php if (!$hasFilter): ?>
    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-num"><?= $stats['total'] ?></span>
            <span class="stat-label">文档总数</span>
        </div>
        <div class="stat-item">
            <span class="stat-num"><?= $stats['recent'] ?></span>
            <span class="stat-label">近7天新增</span>
        </div>
        <a href="recycle.php" class="stat-item stat-link">
            <span class="stat-num"><?= $stats['recycle'] ?></span>
            <span class="stat-label">回收站</span>
        </a>
    </div>
    <?php if (!empty($expiringDocs)): ?>
    <div class="expire-alert">
        <div class="expire-alert-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            即将到期（<?= $expireDays ?>天内）
        </div>
        <div class="expire-alert-list">
            <?php foreach ($expiringDocs as $ed):
                $edTitle = $ed['title'];
                $edShort = mb_strlen($edTitle, 'UTF-8') > 15 ? mb_substr($edTitle, 0, 15, 'UTF-8') . '…' : $edTitle;
            ?>
            <a href="view.php?id=<?= (int)$ed['id'] ?>" class="expire-alert-item<?= strtotime($ed['expire_date']) < time() ? ' expired' : '' ?>">
                <span class="expire-alert-name" title="<?= e($edTitle) ?>"><?= e($edShort) ?></span>
                <span class="expire-alert-date"><?= date('m-d', strtotime($ed['expire_date'])) ?><?= strtotime($ed['expire_date']) < time() ? '（已过期）' : '' ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="search-box">
        <form method="get" action="">
            <div class="search-row">
                <div class="search-input-wrap">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="search" name="q" placeholder="搜索标题、内容..." value="<?= e($search) ?>">
                </div>
                <div class="select-wrap">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">全部分类</option>
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">搜索</button>
                <?php if ($search !== ''): ?>
                <a href="<?= clear_search_url() ?>" class="btn btn-secondary">清除</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($selectedTags)): ?>
            <input type="hidden" name="tags" value="<?= e(implode(',', $selectedTags)) ?>">
            <?php endif; ?>
        </form>
    </div>

    <?php if ($allTags): ?>
    <div class="tag-filter" id="tagFilter">
        <div class="tag-filter-trigger" onclick="document.getElementById('tagFilter').classList.toggle('open')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
            <span class="tag-filter-text">
                <?php if (!empty($selectedTags)): ?>
                    已选 <?= count($selectedTags) ?> 个：<?= e(implode('、', $selectedTags)) ?>
                <?php else: ?>
                    全部标签
                <?php endif; ?>
            </span>
            <svg class="tag-filter-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
        <div class="tag-filter-panel">
            <div class="tag-filter-list">
                <?php foreach ($allTags as $t): ?>
                <a href="<?= tag_toggle_url($t) ?>" class="tag-filter-item <?= in_array($t, $selectedTags) ? 'active' : '' ?>"><?= e($t) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($selectedTags)): ?>
            <div class="tag-filter-panel-footer">
                <a href="<?= clear_all_tags_url() ?>" class="tag-filter-clear">清除全部</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="sort-bar">
        <span class="sort-label">排序：</span>
        <div class="sort-btns">
            <?php
            $baseQ = array_filter(['q' => $search, 'category' => $category, 'tags' => !empty($selectedTags) ? implode(',', $selectedTags) : '']);
            $sortUrl = function($s) use ($baseQ, $order) {
                return '?' . http_build_query(array_merge($baseQ, ['sort' => $s, 'order' => $order]));
            };
            $orderUrl = '?' . http_build_query(array_merge($baseQ, ['sort' => $sort, 'order' => $order === 'desc' ? 'asc' : 'desc']));
            ?>
            <a href="<?= $sortUrl('created_at') ?>" class="sort-btn<?= $sort === 'created_at' ? ' active' : '' ?>">创建时间</a>
            <a href="<?= $sortUrl('updated_at') ?>" class="sort-btn<?= $sort === 'updated_at' ? ' active' : '' ?>">更新时间</a>
            <a href="<?= $orderUrl ?>" class="sort-btn sort-order" title="<?= $order === 'desc' ? '当前倒序，点击切换升序' : '当前升序，点击切换倒序' ?>">
                <?php if ($order === 'desc'): ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="4" x2="12" y2="20"></line><polyline points="6 14 12 20 18 14"></polyline></svg>
                <?php else: ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="4"></line><polyline points="6 10 12 4 18 10"></polyline></svg>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <?php if (empty($docs)): ?>
    <div class="empty">
        <div class="empty-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg></div>
        <?php if ($hasFilter): ?>
        <p>没有找到相关文档，换个关键词试试吧</p>
        <a href="index.php" class="btn btn-secondary btn-small" style="margin-top:15px;">清除搜索</a>
        <?php else: ?>
        <p>暂无文档，点击右上角添加</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($docs as $doc): ?>
    <?php $firstImg = $firstImages[$doc['id']] ?? ''; ?>
    <div class="card">
        <?php if ($firstImg): ?>
        <a href="view.php?id=<?= (int)$doc['id'] ?>" class="card-img-link"><img src="<?= e(UPLOAD_URL . $firstImg) ?>" class="card-img" alt="" loading="lazy"></a>
        <?php else: ?>
        <a href="view.php?id=<?= (int)$doc['id'] ?>" class="card-img-link"><div class="card-img card-img-placeholder"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></div></a>
        <?php endif; ?>
        <div class="card-body">
            <div class="card-title">
                    <a href="view.php?id=<?= (int)$doc['id'] ?>" class="card-title-link"><?= highlight(e($doc['title']), $search) ?></a>
                </div>
            <div class="card-meta">
                <?php if ($doc['category']): ?>
                <a href="?<?= e(http_build_query(array_merge($_GET, ['category' => $doc['category'], 'page' => 1]))) ?>" class="card-cat">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    <?= e($doc['category']) ?>
                </a>
                <?php endif; ?>
                <span class="card-time">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <?= formatDate($doc['updated_at']) ?>
                </span>
            </div>
            <div class="card-desc">
                <?php
                $summary = plain_summary($doc['content']);
                echo highlight(e($summary), $search) ?: '（无文字内容）';
                ?>
            </div>
            <?php if ($doc['tags']): ?>
            <?php $tagList = array_filter(array_map('trim', preg_split('/[,，]/u', $doc['tags']))); ?>
            <?php if ($tagList): ?>
            <div class="card-tags">
                <svg class="card-tags-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                <?php foreach ($tagList as $t): ?>
                <a href="<?= tag_toggle_url($t) ?>" class="tag <?= in_array($t, $selectedTags) ? 'tag-active' : '' ?>" title="点击筛选此标签"><?= highlight(e($t), [$search]) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <div class="page-nav">
            <?php if ($page > 1): ?>
            <a href="<?= page_url(1) ?>" class="page-btn page-first" title="第一页">«</a>
            <a href="<?= page_url($page - 1) ?>" class="page-btn" title="上一页">‹</a>
            <?php else: ?>
            <span class="page-btn page-first disabled">«</span>
            <span class="page-btn disabled">‹</span>
            <?php endif; ?>

            <span class="page-numbers">
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1) {
                echo '<a href="' . page_url(1) . '" class="page-btn">1</a>';
                if ($start > 2) echo '<span class="page-dots">…</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                $far = (abs($i - $page) > 1) ? ' page-far' : '';
                if ($i == $page) {
                    echo '<span class="page-btn active' . $far . '">' . $i . '</span>';
                } else {
                    echo '<a href="' . page_url($i) . '" class="page-btn' . $far . '">' . $i . '</a>';
                }
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="page-dots">…</span>';
                echo '<a href="' . page_url($totalPages) . '" class="page-btn">' . $totalPages . '</a>';
            }
            ?>
            </span>

            <?php if ($page < $totalPages): ?>
            <a href="<?= page_url($page + 1) ?>" class="page-btn" title="下一页">›</a>
            <a href="<?= page_url($totalPages) ?>" class="page-btn page-last" title="最后一页">»</a>
            <?php else: ?>
            <span class="page-btn disabled">›</span>
            <span class="page-btn page-last disabled">»</span>
            <?php endif; ?>
        </div>

        <div class="page-side">
            <span class="page-info">共 <?= $total ?> 条</span>
            <form method="get" action="" class="page-jump-form">
                <?php foreach ($_GET as $k => $v): if ($k !== 'page' && $v !== ''): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v) ? implode(',', $v) : $v) ?>">
                <?php endif; endforeach; ?>
                <span class="page-jump-label">跳至</span>
                <input type="number" name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" class="page-jump-input">
                <span class="page-jump-label">页</span>
                <button type="submit" class="btn btn-small page-jump-btn">跳转</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="js/common.js"></script>
<footer class="site-footer">
    <p>PaperBox 纸质文档管理系统 &copy; <?= date('Y') ?></p>
</footer>
</body>
</html>
