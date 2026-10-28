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

    // Multipart throughout: the picture travels with the details, and the token goes in the form like any other field
    async function post(form) {
        form.append('csrf_token', window.csrfToken);
        const response = await fetch(actionUrl, { method: 'POST', body: form });

        return response.json();
    }

    function showLinkFields() {
        const type = byId('slideLinkType').value;
        byId('slideLinkPageWrap').classList.toggle('d-none', type !== 'page');
        byId('slideLinkUrlWrap').classList.toggle('d-none', type !== 'url');
    }

    function openModal(slide) {
        clear('slideResult');

        const isEdit = Boolean(slide);
        byId('slideModalTitle').textContent = isEdit ? 'Edit Slide' : 'Add Slide';
        byId('slideId').value = isEdit ? slide.id : '';
        byId('slideTitle').value = isEdit ? slide.title : '';
        byId('slideGroup').value = isEdit ? slide.groupName : '';
        byId('slideSummary').value = isEdit ? slide.summary : '';
        byId('slideImage').value = '';
        byId('slideImage').required = !isEdit;
        byId('slidePictureHint').textContent = isEdit ? '(leave empty to keep the current one)' : '';
        byId('slideLinkType').value = isEdit ? slide.linkType : 'none';
        if (isEdit && slide.linkPageId !== '') byId('slideLinkPage').value = slide.linkPageId;
        byId('slideLinkUrl').value = isEdit ? slide.linkUrl : '';
        byId('slideButtonText').value = isEdit ? slide.buttonText : '';
        byId('slideSortOrder').value = isEdit ? slide.sortOrder : 0;
        byId('slideScheduleStart').value = isEdit ? slide.scheduleStart : '';
        byId('slideScheduleEnd').value = isEdit ? slide.scheduleEnd : '';
        byId('slideRecurring').checked = isEdit ? slide.recurring : false;
        byId('slideNotes').value = isEdit ? slide.notes : '';
        byId('slideEnabled').checked = isEdit ? slide.enabled : true;
        byId('slideExclusive').checked = isEdit ? slide.exclusive : false;
        showLinkFields();

        new bootstrap.Modal(byId('slideModal')).show();
    }

    function buildSlideForm() {
        const id = byId('slideId').value;
        const form = new FormData();
        form.append('action', id ? 'update' : 'create');
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

        const image = byId('slideImage').files[0];
        if (image) form.append('image', image);

        return form;
    }

    async function handleSlideSubmit(e) {
        e.preventDefault();
        clear('slideResult');

        const btn = byId('slideSaveButton');
        btn.disabled = true;

        try {
            const result = await post(buildSlideForm());
            if (result.success) {
                window.location.reload();
                return;
            }

            show('slideResult', 'danger', result.error || 'Failed to save');
        } catch (error) {
            show('slideResult', 'danger', 'An error occurred while saving');
        } finally {
            btn.disabled = false;
        }
    }

    async function handleSizeSubmit(e) {
        e.preventDefault();
        clear('sizeResult');

        const form = new FormData();
        form.append('action', 'save_size');
        form.append('width', byId('slideWidth').value);
        form.append('height', byId('slideHeight').value);

        try {
            const result = await post(form);
            if (result.success) {
                window.location.reload();
                return;
            }

            show('sizeResult', 'danger', result.error || 'Failed to save');
        } catch (error) {
            show('sizeResult', 'danger', 'An error occurred while saving');
        }
    }

    function init() {
        byId('addSlideButton').addEventListener('click', function () {
            openModal(null);
        });

        // Row actions come from the ListView; Edit fires list:action with the row's id
        document.addEventListener('list:action', function (e) {
            if (e.detail.action !== 'edit') return;
            openModal(config.slides[e.detail.id]);
        });

        byId('slideLinkType').addEventListener('change', showLinkFields);
        byId('slideForm').addEventListener('submit', handleSlideSubmit);
        byId('sizeForm').addEventListener('submit', handleSizeSubmit);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
