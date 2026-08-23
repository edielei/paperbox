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
