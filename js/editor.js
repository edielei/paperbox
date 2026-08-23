// editor.js — 添加/编辑页公共脚本：图片上传、拖拽排序、Markdown 预览、快捷提交、草稿自动保存
(function() {
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const fileInput = document.getElementById('fileInput');
    const addImgBtn = document.getElementById('addImgBtn');
    const imageList = document.getElementById('imageList');
    const imageOrder = document.getElementById('imageOrder');
    const mainForm = document.getElementById('mainForm');

    const titleInput = document.querySelector('input[name="title"]');
    const categoryInput = document.querySelector('input[name="category"]');
    const tagsInput = document.querySelector('input[name="tags"]');

    // 分类提示：点击填入，用户输入则隐藏
    const catSuggest = document.getElementById('catSuggest');
    if (catSuggest && categoryInput) {
        catSuggest.querySelector('.cat-suggest-btn').addEventListener('click', function() {
            categoryInput.value = catSuggest.querySelector('.cat-suggest-text').textContent;
            catSuggest.style.display = 'none';
            categoryInput.focus();
            scheduleSave();
        });
        categoryInput.addEventListener('input', function() {
            if (this.value.trim()) catSuggest.style.display = 'none';
        });
    }

    // ========== 草稿自动保存 ==========
    const isEditPage = window.location.pathname.toLowerCase().includes('edit.php');
    const docId = isEditPage ? new URLSearchParams(window.location.search).get('id') : null;
    const DRAFT_KEY = isEditPage ? 'paperbox_draft_edit_' + docId : 'paperbox_draft_add';
    let draftReady = false; // 初始化完成后才允许自动保存
    let originalImages = []; // 编辑页原有图片路径，用于区分新增图片

    // 删除草稿中未使用的图片（不恢复时调用）
    async function deleteDraftImages(draftImages) {
        if (!draftImages) return;
        const paths = draftImages.split(',').filter(Boolean);
        // 编辑页只删除新增的图片（不在原有图片列表中的）
        const toDelete = isEditPage
            ? paths.filter(function(p) { return originalImages.indexOf(p) === -1; })
            : paths;
        for (const path of toDelete) {
            try {
                const fd = new FormData();
                fd.append('path', path);
                fd.append('csrf_token', csrf);
                await fetch('delete_image.php', { method: 'POST', body: fd });
            } catch (e) {}
        }
    }

    function saveDraft() {
        if (!draftReady) return;
        const draft = {
            title: titleInput ? titleInput.value : '',
            category: categoryInput ? categoryInput.value : '',
            tags: tagsInput ? tagsInput.value : '',
            content: contentInput.value,
            images: imageOrder.value,
            savedAt: Date.now()
        };
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } catch (e) {}
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function restoreDraft() {
        let raw;
        try { raw = localStorage.getItem(DRAFT_KEY); } catch (e) { return false; }
        if (!raw) return false;
        try {
            const draft = JSON.parse(raw);
            if (!draft.title && !draft.category && !draft.tags && !draft.content && !draft.images) {
                clearDraft();
                return false;
            }
            const time = new Date(draft.savedAt).toLocaleString('zh-CN');
            const msg = isEditPage
                ? '检测到未保存的编辑草稿（' + time + '），是否恢复？\n标题：' + (draft.title || '（无标题）') + '\n（恢复将覆盖当前已加载内容）'
                : '检测到未保存的草稿（' + time + '），是否恢复？\n标题：' + (draft.title || '（无标题）');
            if (!confirm(msg)) {
                deleteDraftImages(draft.images); // 删除草稿中已上传但未使用的图片
                clearDraft();
                return false;
            }
            if (titleInput) titleInput.value = draft.title || '';
            if (categoryInput) categoryInput.value = draft.category || '';
            if (tagsInput) tagsInput.value = draft.tags || '';
            contentInput.value = draft.content || '';
            // 恢复图片（编辑页先清空已有图片）
            if (isEditPage) imageList.innerHTML = '';
            if (draft.images) {
                draft.images.split(',').filter(Boolean).forEach(function(path) {
                    addImageItem(path, 'uploads/' + path);
                });
            }
            return true;
        } catch (e) {
            clearDraft();
            return false;
        }
    }

    // 输入时自动保存（debounce 500ms）
    let draftTimer = null;
    function scheduleSave() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 500);
    }
    [titleInput, categoryInput, tagsInput].forEach(function(el) {
        if (el) el.addEventListener('input', scheduleSave);
    });

    // 表单提交时清除草稿
    mainForm.addEventListener('submit', function() {
        clearDraft();
    });

    // ========== 图片上传 ==========
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
        saveDraft(); // 图片变化时保存草稿
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

    // ========== 编辑器双模式（编辑/预览） ==========
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
        if (mdToolbar) mdToolbar.style.display = '';
    });

    btnPreview.addEventListener('click', function() {
        btnPreview.classList.add('active');
        btnEdit.classList.remove('active');
        contentInput.style.display = 'none';
        previewPane.style.display = 'block';
        if (mdToolbar) mdToolbar.style.display = 'none';
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
        scheduleSave(); // 内容输入时保存草稿
        if (previewPane.style.display === 'block') {
            renderPreview();
        }
    });

    // ========== Markdown 工具栏 ==========
    const mdToolbar = document.getElementById('mdToolbar');
    if (mdToolbar) {
        // 标题下拉菜单
        const headingDropdown = mdToolbar.querySelector('.md-tool-dropdown');
        if (headingDropdown) {
            headingDropdown.querySelector('.md-tool-btn').addEventListener('click', function(e) {
                e.stopPropagation();
                headingDropdown.classList.toggle('open');
            });
            headingDropdown.querySelectorAll('.md-tool-menu button').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const level = parseInt(item.dataset.heading);
                    setHeading(level);
                    headingDropdown.classList.remove('open');
                });
            });
        }
        // 点击页面其他地方关闭下拉菜单
        document.addEventListener('click', function() {
            if (headingDropdown) headingDropdown.classList.remove('open');
        });

        mdToolbar.addEventListener('click', function(e) {
            const btn = e.target.closest('.md-tool-btn');
            if (!btn || btn.closest('.md-tool-dropdown')) return;
            e.preventDefault();
            contentInput.focus();
            const action = btn.dataset.action;
            switch (action) {
                case 'bold': wrapText('**', '**', '粗体文字'); break;
                case 'italic': wrapText('*', '*', '斜体文字'); break;
                case 'underline': wrapText('++', '++', '下划线文字'); break;
                case 'highlight': wrapText('==', '==', '高亮文字'); break;
                case 'code': wrapText('`', '`', '代码'); break;
                case 'sup': wrapText('^', '^', '上标'); break;
                case 'sub': wrapText('~', '~', '下标'); break;
                case 'link': wrapText('[', '](https://)', '链接文字'); break;
                case 'image': wrapText('![', '](https://)', '图片描述'); break;
                case 'quote': prependLine('> '); break;
                case 'ul': toggleList('- '); break;
                case 'ol': toggleList('1. '); break;
                case 'codeblock': wrapCodeBlock(); break;
                case 'table': insertBlock('| 列1 | 列2 | 列3 |\n| --- | --- | --- |\n| 内容 | 内容 | 内容 |'); break;
                case 'hr': insertBlock('\n---\n'); break;
            }
        });
    }

    // 包裹选中文本（无选中则插入占位符并选中）
    function wrapText(before, after, placeholder) {
        const ta = contentInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end);
        const text = selected || placeholder || '';
        ta.value = ta.value.substring(0, start) + before + text + after + ta.value.substring(end);
        ta.focus();
        if (selected) {
            ta.selectionStart = start + before.length;
            ta.selectionEnd = start + before.length + selected.length;
        } else {
            ta.selectionStart = start + before.length;
            ta.selectionEnd = start + before.length + text.length;
        }
        ta.dispatchEvent(new Event('input'));
    }

    // 在当前行首插入前缀
    function prependLine(prefix) {
        const ta = contentInput;
        const start = ta.selectionStart;
        const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
        ta.value = ta.value.substring(0, lineStart) + prefix + ta.value.substring(lineStart);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = start + prefix.length;
        ta.dispatchEvent(new Event('input'));
    }

    // 设置标题级别：已是标题则替换级别，否则新增
    function setHeading(level) {
        const ta = contentInput;
        const start = ta.selectionStart;
        const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
        let lineEnd = ta.value.indexOf('\n', start);
        if (lineEnd === -1) lineEnd = ta.value.length;
        let line = ta.value.substring(lineStart, lineEnd);
        // 移除已有的 # 前缀
        line = line.replace(/^#+\s*/, '');
        const prefix = '#'.repeat(level) + ' ';
        const newLine = prefix + line;
        ta.value = ta.value.substring(0, lineStart) + newLine + ta.value.substring(lineEnd);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = lineStart + prefix.length;
        ta.dispatchEvent(new Event('input'));
    }

    // 切换列表：选中多行则批量加前缀，单行则在行首加
    function toggleList(prefix) {
        const ta = contentInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end);
        if (selected && selected.indexOf('\n') !== -1) {
            const lines = selected.split('\n');
            const newLines = lines.map(function(line) {
                return line.trim() ? prefix + line : line;
            });
            const newText = newLines.join('\n');
            ta.value = ta.value.substring(0, start) + newText + ta.value.substring(end);
            ta.focus();
            ta.selectionStart = start;
            ta.selectionEnd = start + newText.length;
        } else {
            prependLine(prefix);
            return;
        }
        ta.dispatchEvent(new Event('input'));
    }

    // 插入块级内容（在光标处换行插入）
    function insertBlock(text) {
        const ta = contentInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const needsNewline = start > 0 && ta.value[start - 1] !== '\n';
        const insert = (needsNewline ? '\n' : '') + text + '\n';
        ta.value = ta.value.substring(0, start) + insert + ta.value.substring(end);
        ta.focus();
        const cursorPos = start + insert.length;
        ta.selectionStart = ta.selectionEnd = cursorPos;
        ta.dispatchEvent(new Event('input'));
    }

    // 代码块：有选中则包裹，无选中则插入模板
    function wrapCodeBlock() {
        const ta = contentInput;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end);
        if (selected) {
            const before = '\n```\n';
            const after = '\n```\n';
            ta.value = ta.value.substring(0, start) + before + selected + after + ta.value.substring(end);
            ta.focus();
            ta.selectionStart = start + before.length;
            ta.selectionEnd = start + before.length + selected.length;
            ta.dispatchEvent(new Event('input'));
        } else {
            insertBlock('```\n代码内容\n```');
        }
    }

    // Ctrl+Enter / Cmd+Enter 快捷提交
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            clearDraft();
            mainForm.submit();
        }
    });

    // ========== 初始化 ==========
    // 编辑页先加载已有图片，并记录原有图片列表
    const existingData = imageList.dataset.existing;
    if (existingData) {
        try {
            const existing = JSON.parse(existingData);
            originalImages = existing.map(function(img) { return img.path; });
            existing.forEach(function(img) {
                addImageItem(img.path, img.url);
            });
        } catch (e) {}
    }

    // 仅添加页使用草稿功能，编辑页不读取/保存草稿
    if (!isEditPage) {
        restoreDraft();
        draftReady = true;
    }
})();
