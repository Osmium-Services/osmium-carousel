<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Modules\Admin\Services\OsmiumAdmin;
use Osmium\Services\OsmiumCarousel\Models\Slide;
use Osmium\Services\OsmiumCarousel\Models\CarouselConfig;
use Osmium\Services\OsmiumCarousel\Models\SlideImage;
use Osmium\Services\OsmiumCarousel\Models\SlideSchedule;
use Osmium\Services\OsmiumCarousel\Models\SlideText;

/**
 * Carousel controller - manages the slides a theme shows and the one slide size the whole site uses.
 *
 * Routes:
 *   - index()  → /admin/carousel/
 *   - action() → /admin/carousel/action/   (AJAX CRUD; multipart when a picture is sent)
 */
class CarouselController extends AdminController
{
    private const RECORD_TYPE = 'carousel_slide';

    // =========================================================================
    // Public Actions
    // =========================================================================

    public function index(): void
    {
        $model = new Slide($this->osmium->dataSource);
        $today = \date('Y-m-d');

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/carousel.php';
        $this->data['admin']['slides'] = \array_map(fn(array $row): array => $this->formatSlide(row: $row, today: $today), $model->listAll());
        $this->data['admin']['recentSlides'] = \array_map($this->formatRecentSlide(...), $model->listRecent());
        $this->data['admin']['deletedSlides'] = \array_map($this->formatDeletedSlide(...), $model->listDeleted());
        $this->data['admin']['pages'] = $model->listPages();
        $this->data['admin']['size'] = CarouselConfig::get();
        $this->data['admin']['sizeLimits'] = ['min' => CarouselConfig::MIN_SIDE, 'max' => CarouselConfig::MAX_SIDE];

        $this->setView('carousel/index.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $needsId = !\in_array($action, ['create', 'purge_deleted', 'save_size'], true);
        $missingParams = !$action || ($needsId && !$id);
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = match ($action) {
                'create' => $this->createSlide($input),
                'update' => $this->updateSlide(id: $id, input: $input),
                'enable' => $this->setEnabled(id: $id, enabled: true),
                'disable' => $this->setEnabled(id: $id, enabled: false),
                'delete' => $this->deleteSlide($id),
                'restore' => $this->restoreSlide($id),
                'permanently_delete' => $this->permanentlyDelete($id),
                'purge_deleted' => $this->purgeDeleted(),
                'save_size' => $this->saveSize($input),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function createSlide(array $input): array
    {
        $details = $this->validateDetails($input);
        $upload = $this->validateUpload();
        $noPicture = $upload === null;
        if ($noPicture) throw new \InvalidArgumentException('A slide needs a picture');

        $model = new Slide($this->osmium->dataSource);
        $saved = $this->savePicture($upload);
        $newId = $model->create($details);
        $model->setImage(id: $newId, imageFilename: $saved['name'], width: $saved['width'], height: $saved['height']);

        $this->logChange(action: 'Created', id: $newId, name: $details['title']);

        return ['success' => true, 'id' => $newId];
    }

    private function updateSlide(int $id, array $input): array
    {
        $details = $this->validateDetails($input);
        $upload = $this->validateUpload();
        $model = new Slide($this->osmium->dataSource);

        $existing = $model->getById($id);
        if (!$existing) throw new \InvalidArgumentException('Slide not found');

        $hasNewPicture = $upload !== null;
        $saved = $hasNewPicture ? $this->savePicture($upload) : null;

        $model->update(id: $id, details: $details);

        if ($saved !== null) {
            $model->setImage(id: $id, imageFilename: $saved['name'], width: $saved['width'], height: $saved['height']);
            SlideImage::remove(name: $existing['image_filename'], root: $this->root());
        }

        $this->logChange(action: 'Updated', id: $id, name: $details['title']);

        return ['success' => true, 'id' => $id];
    }

    private function setEnabled(int $id, bool $enabled): array
    {
        $model = new Slide($this->osmium->dataSource);
        $slide = $model->getById($id);
        if (!$slide) throw new \InvalidArgumentException('Slide not found');

        $model->setEnabled(id: $id, enabled: $enabled);
        $this->logChange(action: $enabled ? 'Enabled' : 'Disabled', id: $id, name: $slide['title']);

        return ['success' => true, 'id' => $id];
    }

    private function deleteSlide(int $id): array
    {
        $model = new Slide($this->osmium->dataSource);
        $slide = $model->getById($id);
        if (!$slide) throw new \InvalidArgumentException('Slide not found');

        $model->softDelete($id);
        $this->logChange(action: 'Deleted', id: $id, name: $slide['title']);

        return ['success' => true, 'id' => $id];
    }

    private function restoreSlide(int $id): array
    {
        $model = new Slide($this->osmium->dataSource);
        $slide = $model->getAnyById($id);
        if (!$slide) throw new \InvalidArgumentException('Slide not found');

        $model->restore($id);
        $this->logChange(action: 'Restored', id: $id, name: $slide['title']);

        return ['success' => true, 'id' => $id];
    }

    private function permanentlyDelete(int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new Slide($this->osmium->dataSource);
        $slide = $model->getAnyById($id);
        if (!$slide) throw new \InvalidArgumentException('Slide not found');

        $this->logChange(action: 'Permanently deleted', id: $id, name: $slide['title']);
        SlideImage::remove(name: $slide['image_filename'], root: $this->root());
        $model->hardDelete($id);

        return ['success' => true, 'id' => $id];
    }

    private function purgeDeleted(): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new Slide($this->osmium->dataSource);
        foreach ($model->listDeleted() as $slide) {
            SlideImage::remove(name: $slide['image_filename'], root: $this->root());
        }

        $count = $model->purgeDeleted();
        $this->admin->model->changelog->log(description: "Purged {$count} deleted slide(s)", recordType: self::RECORD_TYPE, recordId: 0);

        return ['success' => true, 'count' => $count];
    }

    private function saveSize(array $input): array
    {
        $width = (int) ($input['width'] ?? 0);
        $height = (int) ($input['height'] ?? 0);

        $outOfRange = !CarouselConfig::validSide($width) || !CarouselConfig::validSide($height);
        if ($outOfRange) {
            throw new \InvalidArgumentException('Width and height must each be between ' . CarouselConfig::MIN_SIDE . ' and ' . CarouselConfig::MAX_SIDE . ' pixels');
        }

        CarouselConfig::save(width: $width, height: $height);
        $this->admin->model->changelog->log(description: "Slide size set to {$width} x {$height}", recordType: 'settings', recordId: 0);

        return ['success' => true];
    }

    /**
     * @return array{title: string, summary: ?string, buttonText: ?string, linkType: string, linkPageId: ?int, linkUrl: ?string, enabled: bool, sortOrder: int, scheduleStart: ?string, scheduleEnd: ?string, recurring: bool, exclusive: bool, notes: ?string}
     */
    private function validateDetails(array $input): array
    {
        $title = \trim((string) ($input['title'] ?? ''));
        $titleMissing = $title === '';
        if ($titleMissing) throw new \InvalidArgumentException('Title is required');

        $groupName = SlideText::nullIfEmpty((string) ($input['group_name'] ?? ''));
        $groupTooLong = $groupName !== null && \mb_strlen($groupName) > 100;
        if ($groupTooLong) throw new \InvalidArgumentException('Group must be 100 characters or fewer');

        $summary = SlideText::nullIfEmpty((string) ($input['summary'] ?? ''));
        $buttonText = SlideText::nullIfEmpty((string) ($input['button_text'] ?? ''));
        $tooLong = \mb_strlen($title) > 150 || \mb_strlen((string) $summary) > 255 || \mb_strlen((string) $buttonText) > 50;
        if ($tooLong) throw new \InvalidArgumentException('Title is limited to 150 characters, text to 255 and the button to 50');

        $link = $this->validateLink($input);
        $dates = $this->validateDates($input);

        return [
            'title' => $title,
            'groupName' => $groupName,
            'summary' => $summary,
            'buttonText' => $buttonText,
            'linkType' => $link['type'],
            'linkPageId' => $link['pageId'],
            'linkUrl' => $link['url'],
            'enabled' => !empty($input['enabled']),
            'sortOrder' => \max(0, (int) ($input['sort_order'] ?? 0)),
            'scheduleStart' => $dates['start'],
            'scheduleEnd' => $dates['end'],
            'recurring' => !empty($input['schedule_recurring']),
            'exclusive' => !empty($input['exclusive']),
            'notes' => SlideText::nullIfEmpty((string) ($input['promotion_notes'] ?? '')),
        ];
    }

    /**
     * @return array{type: string, pageId: ?int, url: ?string}
     */
    private function validateLink(array $input): array
    {
        $type = (string) ($input['link_type'] ?? 'none');
        $typeUnknown = !\in_array($type, ['none', 'page', 'url'], true);
        if ($typeUnknown) throw new \InvalidArgumentException('Unknown link type');

        if ($type === 'page') {
            $pageId = (int) ($input['link_page_id'] ?? 0);
            $pageIds = \array_map('intval', \array_column((new Slide($this->osmium->dataSource))->listPages(), 'id'));
            $pageMissing = !\in_array($pageId, $pageIds, true);
            if ($pageMissing) throw new \InvalidArgumentException('Choose a page to link to');

            return ['type' => 'page', 'pageId' => $pageId, 'url' => null];
        }

        if ($type === 'url') {
            $url = \trim((string) ($input['link_url'] ?? ''));
            $urlInvalid = !SlideText::isValidLink($url);
            if ($urlInvalid) throw new \InvalidArgumentException('The link must be a web address starting http:// or https://, or a path on this site starting with /');

            return ['type' => 'url', 'pageId' => null, 'url' => $url];
        }

        return ['type' => 'none', 'pageId' => null, 'url' => null];
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    private function validateDates(array $input): array
    {
        $start = SlideText::nullIfEmpty((string) ($input['schedule_start'] ?? ''));
        $end = SlideText::nullIfEmpty((string) ($input['schedule_end'] ?? ''));

        $startBad = $start !== null && !SlideText::isDate($start);
        $endBad = $end !== null && !SlideText::isDate($end);
        if ($startBad || $endBad) throw new \InvalidArgumentException('Dates must be real dates');

        $recurring = !empty($input['schedule_recurring']);
        $endsBeforeStart = $start !== null && $end !== null && $end < $start;
        if ($endsBeforeStart && !$recurring) throw new \InvalidArgumentException('The end date cannot be before the start date');

        return ['start' => $start, 'end' => $end];
    }

    /**
     * The uploaded picture checked and ready to save, or null when none was sent
     *
     * @return array{tmp_name: string, mimeType: string}|null
     */
    private function validateUpload(): ?array
    {
        $file = $_FILES['image'] ?? null;
        $noFileSent = $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if ($noFileSent) return null;

        $uploadFailed = $file['error'] !== UPLOAD_ERR_OK;
        if ($uploadFailed) throw new \InvalidArgumentException('The picture did not upload');

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $typeNotAllowed = !\in_array($mimeType, SlideImage::TYPES, true);
        if ($typeNotAllowed) throw new \InvalidArgumentException('The picture must be a JPG, PNG or WebP file');

        $maxUpload = OsmiumAdmin::getMaxUploadSize();
        $tooLarge = $file['size'] > $maxUpload['bytes'];
        if ($tooLarge) throw new \InvalidArgumentException('The picture is too large. Maximum ' . $maxUpload['formatted']);

        return ['tmp_name' => $file['tmp_name'], 'mimeType' => $mimeType];
    }

    /**
     * @param array{tmp_name: string, mimeType: string} $upload
     * @return array{name: string, width: int, height: int}
     */
    private function savePicture(array $upload): array
    {
        $size = CarouselConfig::get();
        $name = \bin2hex(\random_bytes(8));

        $saved = SlideImage::save(
            sourcePath: $upload['tmp_name'],
            mimeType: $upload['mimeType'],
            name: $name,
            width: $size['width'],
            height: $size['height'],
            root: $this->root(),
        );

        return ['name' => $name] + $saved;
    }

    private function root(): string
    {
        return (string) $_SERVER['DOCUMENT_ROOT'];
    }

    private function formatSlide(array $row, string $today): array
    {
        $label = $row['enabled']
            ? SlideSchedule::label(start: $row['schedule_start'], end: $row['schedule_end'], recurring: (bool) $row['schedule_recurring'], today: $today)
            : 'Off';

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'groupName' => $row['group_name'] ?? '',
            'summary' => $row['summary'] ?? '',
            'buttonText' => $row['button_text'] ?? '',
            'linkType' => $row['link_type'],
            'linkPageId' => $row['link_page_id'] !== null ? (int) $row['link_page_id'] : '',
            'linkUrl' => $row['link_url'] ?? '',
            'enabled' => (bool) $row['enabled'],
            'sortOrder' => (int) $row['sort_order'],
            'scheduleStart' => $row['schedule_start'] ?? '',
            'scheduleEnd' => $row['schedule_end'] ?? '',
            'recurring' => (bool) $row['schedule_recurring'],
            'exclusive' => (bool) $row['exclusive'],
            'notes' => $row['promotion_notes'] ?? '',
            'thumbUrl' => $this->thumbUrl($row['image_filename'] ?? null),
            'statusBadge' => $this->statusBadge($label) . ($row['exclusive'] ? ' <span class="badge bg-label-warning">Exclusive</span>' : ''),
        ];
    }

    private function formatRecentSlide(array $row): array
    {
        $changed = \max((string) ($row['updated_at'] ?? ''), (string) ($row['deleted_at'] ?? ''), (string) $row['created_at']);

        return [
            'id' => (int) $row['id'],
            'deleted_at' => $row['deleted_at'] ?? null,
            'title' => $row['title'],
            'updatedDate' => \date('j M Y, g:ia', \strtotime($changed)),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    private function formatDeletedSlide(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'deletedDate' => \date('d M Y', \strtotime((string) $row['deleted_at'])),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    private function statusBadge(string $label): string
    {
        $colour = match ($label) {
            'Live' => 'success',
            'Scheduled', 'Off season' => 'info',
            default => 'secondary',
        };

        return '<span class="badge bg-label-' . $colour . '">' . \htmlspecialchars($label) . '</span>';
    }

    private function thumbUrl(?string $name): string
    {
        $hasName = !empty($name);
        if (!$hasName) return '';

        return SlideImage::DIR . \basename($name) . '-mobile.jpg';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
