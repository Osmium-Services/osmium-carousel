(function () {
    'use strict';

    const config = window.OsmiumCarouselConfig || {};
    const actionUrl = config.basePath + 'carousel/action/';

    function byId(id) {
        return document.getElementById(id);
    }

    function show(kind, message) {
        const box = byId('slideResult');
        box.className = 'alert alert-' + kind;
        box.textContent = message;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clear() {
        const box = byId('slideResult');
        box.className = 'alert d-none';
        box.textContent = '';
    }

    // Picture drop zone: the chosen file stays in the hidden file input and is sent when the slide is saved
    const zone = byId('slidePictureZone');
    const pictureInput = byId('slideImage');
    const preview = zone.querySelector('.slide-picture-preview');
    const empty = zone.querySelector('.slide-picture-empty');
    const removeButton = zone.querySelector('.slide-picture-remove');
    const currentPicture = config.currentPicture || ''; // The slide's saved picture, which Remove falls back to: a slide always has one
    let objectUrl = null;

    function showPicture(src) {
        if (objectUrl && src !== objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        preview.classList.toggle('d-none', !src);
        empty.classList.toggle('d-none', !!src);
        removeButton.classList.toggle('d-none', !src);
        if (src) preview.src = src;
    }

    function choosePicture(file) {
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            show('danger', 'Drop a JPG, PNG or WebP picture.');
            return;
        }

        clear();
        const transfer = new DataTransfer();
        transfer.items.add(file);
        pictureInput.files = transfer.files;
        objectUrl = URL.createObjectURL(file);
        showPicture(objectUrl);
    }

    function initPictureZone() {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.add('drag-active'); });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.remove('drag-active'); });
        });
        zone.addEventListener('drop', function (e) { choosePicture(e.dataTransfer.files[0]); });
        zone.addEventListener('click', function (e) {
            if (!e.target.closest('.slide-picture-remove')) pictureInput.click();
        });
        pictureInput.addEventListener('change', function () { choosePicture(pictureInput.files[0]); });
        removeButton.addEventListener('click', function (e) {
            e.stopPropagation();
            pictureInput.value = '';
            showPicture(currentPicture);
        });
        showPicture(currentPicture);
    }

    function showLinkFields() {
        const type = byId('slideLinkType').value;
        byId('slideLinkPageWrap').classList.toggle('d-none', type !== 'page');
        byId('slideLinkUrlWrap').classList.toggle('d-none', type !== 'url');
    }

    // Multipart throughout: the picture travels with the details, and the token goes in the form like any other field
    function buildSlideForm() {
        const id = byId('slideId').value;
        const form = new FormData();
        form.append('csrf_token', window.csrfToken);
        form.append('action', Number(id) ? 'update' : 'create');
        form.append('id', id);
        form.append('title', byId('slideTitle').value);
        form.append('group_name', byId('slideGroup').value);
        form.append('summary', byId('slideSummary').value);
        form.append('link_type', byId('slideLinkType').value);
        form.append('link_page_id', byId('slideLinkPage').value);
        form.append('link_url', byId('slideLinkUrl').value);
        form.append('button_text', byId('slideButtonText').value);
        form.append('sort_order', byId('slideSortOrder').value);
        form.append('schedule_start', byId('slideScheduleStart').value);
        form.append('schedule_end', byId('slideScheduleEnd').value);
        form.append('schedule_recurring', byId('slideRecurring').checked ? '1' : '');
        form.append('promotion_notes', byId('slideNotes').value);
        form.append('enabled', byId('slideEnabled').checked ? '1' : '');
        form.append('exclusive', byId('slideExclusive').checked ? '1' : '');

        const image = pictureInput.files[0];
        if (image) form.append('image', image);

        return form;
    }

    // The admin's unsaved-changes warning reads window.hasUnsavedChanges. Its form tracker compares named fields and
    // this form has none, so compare what Save would send against what the page loaded with.
    function formValues() {
        const values = {};
        for (const [key, value] of buildSlideForm().entries()) {
            if (key === 'csrf_token') continue;
            values[key] = value instanceof File ? value.name + ':' + value.size : value;
        }
        return JSON.stringify(values);
    }

    function trackChanges() {
        window.hasUnsavedChanges = formValues() !== loadedValues;
    }

    async function handleSubmit(e) {
        e.preventDefault();
        clear();

        const button = byId('slideSaveButton');
        button.disabled = true;

        try {
            const response = await fetch(actionUrl, { method: 'POST', body: buildSlideForm() });
            const result = await response.json();
            if (result.success) {
                // Go to the slide by id: the page shows the same saved bar as every other edit screen
                window.hasUnsavedChanges = false;
                window.location.href = config.editUrl + '?id=' + result.id;
                return;
            }

            show('danger', result.error || 'Failed to save');
        } catch (error) {
            show('danger', 'An error occurred while saving');
        }
        button.disabled = false;
    }

    initPictureZone();
    showLinkFields();
    const loadedValues = formValues();
    byId('slideForm').addEventListener('input', trackChanges);
    byId('slideForm').addEventListener('change', trackChanges);
    removeButton.addEventListener('click', trackChanges);
    byId('slideLinkType').addEventListener('change', showLinkFields);
    byId('slideForm').addEventListener('submit', handleSubmit);
})();
