<?php
require 'config.php';
$pageTitle = '设置';
$allTags = get_all_tags();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expire_days'])) {
    $days = max(1, min(365, intval($_POST['expire_days'])));
    set_setting('expire_days', $days);
    header('Location: settings.php?saved=1');
    exit;
}
$expireDays = (int)get_setting('expire_days', 30);
$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - PaperBox</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
</head>
<body>
    <div class="container">
    <div class="header">
        <a href="index.php" class="back-btn" title="返回">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
        <h1><?= e($pageTitle) ?></h1>
    </div>
        <?php if ($saved): ?>
        <div class="settings-saved">设置已保存</div>
        <?php endif; ?>

        <!-- 提醒设置 -->
        <div class="setting-card">
            <div class="setting-card-header">
                <div class="setting-icon setting-icon-blue">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </div>
                <div class="setting-card-title">
                    <h3>提醒设置</h3>
                    <p>文档到期前，在首页顶部显示提醒</p>
                </div>
            </div>
            <div class="setting-card-body">
                <form method="post" action="">
                    <div class="setting-form-inline">
                        <input type="number" name="expire_days" id="expireDaysInput" value="<?= $expireDays ?>" min="1" max="365" class="input-date setting-input-num">
                        <span class="setting-unit">天</span>
                    </div>
                    <div class="quick-select">
                        <span class="quick-select-label">快速选择</span>
                        <button type="button" class="quick-btn" data-days="7">7天</button>
                        <button type="button" class="quick-btn" data-days="15">15天</button>
                        <button type="button" class="quick-btn" data-days="30">30天</button>
                        <button type="button" class="quick-btn" data-days="90">90天</button>
                    </div>
                    <button type="submit" class="btn btn-primary setting-save-btn">保存</button>
                </form>
            </div>
        </div>

        <!-- 整站备份 -->
        <div class="setting-card">
            <div class="setting-card-header">
                <div class="setting-icon setting-icon-green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </div>
                <div class="setting-card-title">
                    <h3>整站备份</h3>
                    <p>下载全部数据（数据库 + 所有图片）</p>
                </div>
            </div>
            <div class="setting-card-body">
                <div class="setting-warning">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span>文件较多较大，下载可能需要较长时间，请耐心等待</span>
                </div>
                <div class="setting-action-right">
                    <a href="export.php" class="btn btn-primary">开始备份</a>
                </div>
            </div>
        </div>

        <!-- 导出图片 -->
        <div class="setting-card">
            <div class="setting-card-header">
                <div class="setting-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                </div>
                <div class="setting-card-title">
                    <h3>导出图片</h3>
                    <p>按时间段、标签或关键字，导出对应文档的图片</p>
                </div>
            </div>
            <div class="setting-card-body">
                <div class="export-tabs">
                    <button type="button" class="export-tab active" data-tab="date">按时间段</button>
                    <button type="button" class="export-tab" data-tab="tags">按标签</button>
                    <button type="button" class="export-tab" data-tab="keyword">按关键字</button>
                </div>
                <!-- 按时间段 -->
                <div class="export-tab-content active" data-tab="date">
                    <div class="control-date">
                        <div class="setting-form-inline">
                            <input type="date" id="startDate" class="input-date">
                            <span class="setting-unit">至</span>
                            <input type="date" id="endDate" class="input-date">
                        </div>
                        <button class="btn btn-primary" onclick="exportByDate()">导出</button>
                    </div>
                </div>
                <!-- 按标签 -->
                <div class="export-tab-content" data-tab="tags">
                    <div class="control-tags">
                        <div class="tag-check-list" id="tagCheckList">
                            <?php foreach ($allTags as $tag): ?>
                            <label class="tag-check-item">
                                <input type="checkbox" value="<?= e($tag) ?>" class="tag-checkbox">
                                <span class="tag-check-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                                <span><?= e($tag) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-primary" onclick="exportByTags()">导出</button>
                    </div>
                </div>
                <!-- 按关键字 -->
                <div class="export-tab-content" data-tab="keyword">
                    <div class="setting-form-inline control-search">
                        <input type="text" id="searchKeyword" placeholder="输入关键字..." class="input-date setting-input-search">
                        <button class="btn btn-secondary" onclick="searchCount()">查询</button>
                        <button id="searchDownloadBtn" class="btn btn-primary" style="display:none;" onclick="exportBySearch()">下载</button>
                    </div>
                    <div id="searchResult" class="settings-search-result" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <p>PaperBox 纸质文档管理系统 &copy; <?= date('Y') ?></p>
    </footer>

    <script>
    function exportByDate() {
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        if (!s || !e) { alert('请选择开始和结束日期'); return; }
        window.location.href = 'export.php?images=1&start_date=' + s + '&end_date=' + e;
    }
    function exportByTags() {
        const checks = document.querySelectorAll('.tag-checkbox:checked');
        if (checks.length === 0) { alert('请至少选择一个标签'); return; }
        const tags = Array.from(checks).map(c => c.value).join(',');
        window.location.href = 'export.php?images=1&tags=' + encodeURIComponent(tags);
    }
    function searchCount() {
        const kw = document.getElementById('searchKeyword').value.trim();
        if (!kw) { alert('请输入关键字'); return; }
        const resultEl = document.getElementById('searchResult');
        const downloadBtn = document.getElementById('searchDownloadBtn');
        resultEl.style.display = 'block';
        resultEl.textContent = '查询中...';
        fetch('export.php?count=1&images=1&q=' + encodeURIComponent(kw))
            .then(r => r.json())
            .then(data => {
                resultEl.textContent = '共找到 ' + data.count + ' 条相关记录，' + data.images + ' 张图片';
                downloadBtn.style.display = data.count > 0 ? 'inline-flex' : 'none';
            })
            .catch(() => {
                resultEl.textContent = '查询失败，请重试';
                downloadBtn.style.display = 'none';
            });
    }
    function exportBySearch() {
        const kw = document.getElementById('searchKeyword').value.trim();
        if (!kw) return;
        window.location.href = 'export.php?images=1&q=' + encodeURIComponent(kw);
    }
    // 标签选中状态
    document.querySelectorAll('.tag-check-item input[type="checkbox"]').forEach(cb => {
        const update = () => {
            cb.closest('.tag-check-item').classList.toggle('checked', cb.checked);
        };
        cb.addEventListener('change', update);
        update();
    });
    // 快速选择天数
    const expireInput = document.getElementById('expireDaysInput');
    const quickBtns = document.querySelectorAll('.quick-btn');
    function updateQuickActive() {
        const val = parseInt(expireInput.value, 10);
        quickBtns.forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.days, 10) === val);
        });
    }
    quickBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            expireInput.value = btn.dataset.days;
            updateQuickActive();
        });
    });
    expireInput.addEventListener('input', updateQuickActive);
    updateQuickActive();
    // 导出 tab 切换
    document.querySelectorAll('.export-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            document.querySelectorAll('.export-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.export-tab-content').forEach(content => content.classList.remove('active'));
            tab.classList.add('active');
            document.querySelector('.export-tab-content[data-tab="' + target + '"]').classList.add('active');
        });
    });
    </script>
</body>
</html>
