// common.js — 全站公共脚本

// 更多操作菜单
function toggleMore(e) {
    e.stopPropagation();
    document.getElementById('moreDropdown').classList.toggle('show');
}
document.addEventListener('click', function() {
    const dd = document.getElementById('moreDropdown');
    if (dd) dd.classList.remove('show');
});

// 标签筛选面板：点击外部关闭
document.addEventListener('click', function(e) {
    const tf = document.getElementById('tagFilter');
    if (tf && !tf.contains(e.target)) {
        tf.classList.remove('open');
    }
});

// 数字键盘 + 号快捷跳转到添加页
document.addEventListener('keydown', function(e) {
    if (e.code !== 'NumpadAdd') return;
    const tag = document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    if (document.activeElement.isContentEditable) return;
    window.location.href = 'add.php';
});
