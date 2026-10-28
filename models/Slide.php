<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * The slides an admin manages. A slide is soft-deleted first and only removed for good from the bin.
 */
class Slide extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'carousel_slides');
    }

    /** Every live (not binned) slide in display order, with the linked page's url */
    public function listAll(): array
    {
        return $this->rows('slide-list-all.sql');
    }

    /** Enabled slides with a picture, in display order: the public list before the schedule is applied */
    public function listEnabled(): array
    {
        return $this->rows('slide-list-enabled.sql');
    }

    /** Pages a slide can link to: id, url, title */
    public function listPages(): array
    {
        return $this->rows('slide-list-pages.sql');
    }

    public function listRecent(): array
    {
        return $this->rows('slide-list-recent.sql');
    }

    public function listDeleted(): array
    {
        return $this->rows('slide-list-deleted.sql');
    }

    public function getById(int $id): ?array
    {
        return $this->one(file: 'slide-get-by-id.sql', id: $id);
    }

    /** Like getById() but also finds binned slides, for restore and permanent delete */
    public function getAnyById(int $id): ?array
    {
        return $this->one(file: 'slide-get-any.sql', id: $id);
    }

    /**
     * @param array{title: string, groupName: ?string, summary: ?string, buttonText: ?string, linkType: string, linkPageId: ?int, linkUrl: ?string, enabled: bool, sortOrder: int, scheduleStart: ?string, scheduleEnd: ?string, recurring: bool, exclusive: bool, notes: ?string} $details
     */
    public function create(array $details): int
    {
        $this->database->query($this->loadSqlFile('slide-create.sql'));
        $this->bindDetails($details);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    /**
     * @param array{title: string, groupName: ?string, summary: ?string, buttonText: ?string, linkType: string, linkPageId: ?int, linkUrl: ?string, enabled: bool, sortOrder: int, scheduleStart: ?string, scheduleEnd: ?string, recurring: bool, exclusive: bool, notes: ?string} $details
     */
    public function update(int $id, array $details): void
    {
        $this->database->query($this->loadSqlFile('slide-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->bindDetails($details);
        $this->database->execute();
    }

    public function setImage(int $id, string $imageFilename, int $width, int $height): void
    {
        $this->database->query($this->loadSqlFile('slide-set-image.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':image_filename', value: $imageFilename);
        $this->database->bind(param: ':image_width', value: $width);
        $this->database->bind(param: ':image_height', value: $height);
        $this->database->execute();
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->database->query($this->loadSqlFile('slide-set-enabled.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':enabled', value: $enabled ? 1 : 0);
        $this->database->execute();
    }

    public function softDelete(int $id): void
    {
        $this->run(file: 'slide-soft-delete.sql', id: $id);
    }

    public function restore(int $id): void
    {
        $this->run(file: 'slide-restore.sql', id: $id);
    }

    public function hardDelete(int $id): void
    {
        $this->run(file: 'slide-hard-delete.sql', id: $id);
    }

    /** @return int How many slides were removed */
    public function purgeDeleted(): int
    {
        $this->database->query($this->loadSqlFile('slide-purge-deleted.sql'));
        $this->database->execute();

        return $this->database->rowCount();
    }

    private function rows(string $file): array
    {
        $this->database->query($this->loadSqlFile($file));

        return $this->database->resultset();
    }

    private function one(string $file, int $id): ?array
    {
        $this->database->query($this->loadSqlFile($file));
        $this->database->bind(param: ':id', value: $id);
        $result = $this->database->single();

        return $result ?: null;
    }

    private function run(string $file, int $id): void
    {
        $this->database->query($this->loadSqlFile($file));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    private function bindDetails(array $details): void
    {
        $this->database->bind(param: ':title', value: $details['title']);
        $this->database->bind(param: ':group_name', value: $details['groupName']);
        $this->database->bind(param: ':summary', value: $details['summary']);
        $this->database->bind(param: ':button_text', value: $details['buttonText']);
        $this->database->bind(param: ':link_type', value: $details['linkType']);
        $this->database->bind(param: ':link_page_id', value: $details['linkPageId']);
        $this->database->bind(param: ':link_url', value: $details['linkUrl']);
        $this->database->bind(param: ':enabled', value: $details['enabled'] ? 1 : 0);
        $this->database->bind(param: ':sort_order', value: $details['sortOrder']);
        $this->database->bind(param: ':schedule_start', value: $details['scheduleStart']);
        $this->database->bind(param: ':schedule_end', value: $details['scheduleEnd']);
        $this->database->bind(param: ':schedule_recurring', value: $details['recurring'] ? 1 : 0);
        $this->database->bind(param: ':exclusive', value: $details['exclusive'] ? 1 : 0);
        $this->database->bind(param: ':promotion_notes', value: $details['notes']);
    }
}
