(function () {
    'use strict';

    const config = window.OsmiumCarouselConfig || {};
    const actionUrl = config.basePath + 'carousel/action/';

    function byId(id) {
        return document.getElementById(id);
    }

    function show(resultId, kind, message) {
        const box = byId(resultId);
        box.className = 'alert alert-' + kind;
        box.textContent = message;
    }

    function clear(resultId) {
        const box = byId(resultId);
        box.className = 'alert d-none';
        box.textContent = '';
    }

    async function handleSizeSubmit(e) {
        e.preventDefault();
        clear('sizeResult');

        const form = new FormData();
        form.append('csrf_token', window.csrfToken);
        form.append('action', 'save_size');
        form.append('width', byId('slideWidth').value);
        form.append('height', byId('slideHeight').value);

        try {
            const response = await fetch(actionUrl, { method: 'POST', body: form });
            const result = await response.json();
            if (result.success) {
                window.location.reload();
                return;
            }

            show('sizeResult', 'danger', result.error || 'Failed to save');
        } catch (error) {
            show('sizeResult', 'danger', 'An error occurred while saving');
        }
    }

    byId('sizeForm').addEventListener('submit', handleSizeSubmit);
})();
