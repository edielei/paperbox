// editor.js — 添加/编辑页公共脚本：图片上传、拖拽排序、Markdown 预览、快捷提交
(function() {
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const fileInput = document.getElementById('fileInput');
    const addImgBtn = document.getElementById('addImgBtn');
    const imageList = document.getElementById('imageList');
    const imageOrder = document.getElementById('imageOrder');

    addImgBtn.addEventListener('click', function() { fileInput.click(); });

    fileInput.addEventListener('change', async function() {
        const files = Array.from(this.files);
        for (const file of files) {
            await uploadFile(file);
        }
        this.value = '';
    });

    async function uploadFile(file) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('csrf_token', csrf);
        addImgBtn.disabled = true;
        addImgBtn.textContent = '上传中...';
        try {
            const res = await fetch('upload.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                addImageItem(data.path, data.url);
            } else {
                alert(data.error || '上传失败');
            }
        } catch (e) {
            alert('网络错误，上传失败');
        } finally {
            addImgBtn.disabled = false;
            addImgBtn.textContent = '+ 选择图片';
        }
    }

    function addImageItem(path, url) {
        const item = document.createElement('div');
        item.className = 'img-item';
        item.draggable = true;
        item.dataset.path = path;
        item.innerHTML =
            '<img src="' + url + '" alt="">' +
            '<button type="button" class="img-remove" title="删除">×</button>' +
            '<span class="img-drag">⋮⋮</span>';
        item.querySelector('.img-remove').addEventListener('click', function() {
            item.remove();
            updateOrder();
        });
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('drop', handleDrop);
        item.addEventListener('dragend', handleDragEnd);
        imageList.appendChild(item);
        updateOrder();
    }

    function updateOrder() {
        const paths = Array.from(imageList.querySelectorAll('.img-item')).map(function(el) {
            return el.dataset.path;
        });
        imageOrder.value = paths.join(',');
    }

    // 触摸设备拖拽排序
    let touchItem = null, touchClone = null;
    let touchOffsetX = 0, touchOffsetY = 0;
    imageList.addEventListener('touchstart', function(e) {
        const item = e.target.closest('.img-item');
        if (!item || e.target.classList.contains('img-remove')) return;
        touchItem = item;
        const touch = e.touches[0];
        const rect = item.getBoundingClientRect();
        touchOffsetX = touch.clientX - rect.left;
        touchOffsetY = touch.clientY - rect.top;
        touchClone = item.cloneNode(true);
        touchClone.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;opacity:0.85;' +
            'transform:scale(1.05);left:' + rect.left + 'px;top:' + rect.top + 'px;' +
            'width:' + rect.width + 'px;height:' + rect.height + 'px;';
        document.body.appendChild(touchClone);
        item.style.opacity = '0.3';
    }, { passive: true });
    imageList.addEventListener('touchmove', function(e) {
        if (!touchItem || !touchClone) return;
        e.preventDefault();
        const touch = e.touches[0];
        touchClone.style.left = (touch.clientX - touchOffsetX) + 'px';
        touchClone.style.top = (touch.clientY - touchOffsetY) + 'px';
        touchClone.style.display = 'none';
        const below = document.elementFromPoint(touch.clientX, touch.clientY);
        touchClone.style.display = '';
        if (!below) return;
        const target = below.closest('.img-item');
        if (target && target !== touchItem && target.parentNode === imageList) {
            const r = target.getBoundingClientRect();
            if (touch.clientY > r.top + r.height / 2) {
                imageList.insertBefore(touchItem, target.nextSibling);
            } else {
                imageList.insertBefore(touchItem, target);
            }
        }
    }, { passive: false });
    imageList.addEventListener('touchend', function() {
        if (touchItem) touchItem.style.opacity = '';
        if (touchClone) { touchClone.remove(); touchClone = null; }
        touchItem = null;
        updateOrder();
    });

    // 桌面端拖拽排序
    let dragSrc = null;
    function handleDragStart(e) {
        dragSrc = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.path);
        this.classList.add('dragging');
    }
    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
    function handleDrop(e) {
        e.preventDefault();
        if (dragSrc && dragSrc !== this) {
            const items = Array.from(imageList.querySelectorAll('.img-item'));
            const srcIdx = items.indexOf(dragSrc);
            const tgtIdx = items.indexOf(this);
            if (srcIdx < tgtIdx) {
                imageList.insertBefore(dragSrc, this.nextSibling);
            } else {
                imageList.insertBefore(dragSrc, this);
            }
            updateOrder();
        }
    }
    function handleDragEnd() {
        this.classList.remove('dragging');
    }

    // 编辑器双模式（编辑/预览）
    const btnEdit = document.getElementById('btnEdit');
    const btnPreview = document.getElementById('btnPreview');
    const contentInput = document.getElementById('contentInput');
    const previewPane = document.getElementById('previewPane');
    let previewTimer = null;

    btnEdit.addEventListener('click', function() {
        btnEdit.classList.add('active');
        btnPreview.classList.remove('active');
        contentInput.style.display = 'block';
        previewPane.style.display = 'none';
    });

    btnPreview.addEventListener('click', function() {
        btnPreview.classList.add('active');
        btnEdit.classList.remove('active');
        contentInput.style.display = 'none';
        previewPane.style.display = 'block';
        renderPreview();
    });

    function renderPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(async function() {
            const fd = new FormData();
            fd.append('content', contentInput.value);
            try {
                const res = await fetch('preview.php', { method: 'POST', body: fd });
                previewPane.innerHTML = '<div class="markdown-body">' + (await res.text()) + '</div>';
            } catch (e) {
                previewPane.innerHTML = '<p style="color:#999;">预览加载失败</p>';
            }
        }, 300);
    }

    contentInput.addEventListener('input', function() {
        if (previewPane.style.display === 'block') {
            renderPreview();
        }
    });

    // Ctrl+Enter / Cmd+Enter 快捷提交
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('mainForm').submit();
        }
    });

    // 初始化已有图片（编辑页从 data-existing 读取）
    const existingData = imageList.dataset.existing;
    if (existingData) {
        try {
            JSON.parse(existingData).forEach(function(img) {
                addImageItem(img.path, img.url);
            });
        } catch (e) {}
    }
})();
