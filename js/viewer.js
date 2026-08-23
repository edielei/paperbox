// viewer.js — 详情页图片弹窗、缩放、拖拽、展开全部
(function() {
    const gallery = document.getElementById('imgGallery');
    if (!gallery) return;

    let images = [];
    try {
        images = JSON.parse(gallery.dataset.images || '[]');
    } catch (e) { images = []; }

    const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    let currentIdx = 0;
    let scale = 1, translateX = 0, translateY = 0;
    let isDragging = false, startX = 0, startY = 0, startTX = 0, startTY = 0;
    const modalImg = document.getElementById('modalImg');

    // 展开全部图片
    window.showAllImages = function() {
        document.querySelectorAll('.gallery-item.hidden').forEach(function(el) {
            el.classList.remove('hidden');
        });
        const btn = document.querySelector('.load-more-btn');
        if (btn) btn.style.display = 'none';
    };

    function applyTransform() {
        modalImg.style.transform = 'translate(' + translateX + 'px,' + translateY + 'px) scale(' + scale + ')';
    }
    function resetZoom() {
        scale = 1; translateX = 0; translateY = 0;
        applyTransform();
    }

    if (!isTouch) {
        document.querySelectorAll('.gallery-link').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                openModal(parseInt(this.dataset.idx, 10));
            });
        });

        modalImg.addEventListener('wheel', function(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.15 : 0.15;
            const newScale = Math.max(0.5, Math.min(5, scale + delta));
            const rect = modalImg.getBoundingClientRect();
            const cx = e.clientX - rect.left - rect.width / 2;
            const cy = e.clientY - rect.top - rect.height / 2;
            const ratio = newScale / scale;
            translateX -= cx * (ratio - 1);
            translateY -= cy * (ratio - 1);
            scale = newScale;
            applyTransform();
        }, { passive: false });

        modalImg.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isDragging = true;
            startX = e.clientX; startY = e.clientY;
            startTX = translateX; startTY = translateY;
        });
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            translateX = startTX + (e.clientX - startX);
            translateY = startTY + (e.clientY - startY);
            applyTransform();
        });
        document.addEventListener('mouseup', function() { isDragging = false; });
    }

    function openModal(idx) {
        currentIdx = idx;
        resetZoom();
        showModalImage();
        document.getElementById('imgModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    window.closeModal = function() {
        document.getElementById('imgModal').classList.remove('active');
        document.body.style.overflow = '';
    };
    function showModalImage() {
        modalImg.src = images[currentIdx];
        document.getElementById('modalCounter').textContent = (currentIdx + 1) + ' / ' + images.length;
    }
    window.prevImage = function(e) {
        e.stopPropagation();
        currentIdx = (currentIdx - 1 + images.length) % images.length;
        resetZoom();
        showModalImage();
    };
    window.nextImage = function(e) {
        e.stopPropagation();
        currentIdx = (currentIdx + 1) % images.length;
        resetZoom();
        showModalImage();
    };

    document.getElementById('imgModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (!document.getElementById('imgModal').classList.contains('active')) return;
        if (e.key === 'ArrowLeft') prevImage(e);
        if (e.key === 'ArrowRight') nextImage(e);
        if (e.key === 'Escape') closeModal();
    });
})();
