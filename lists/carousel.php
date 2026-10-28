<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Carousel lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (thumbHtml, statusBadge...) are
 * prepared by CarouselController::formatSlide() and formatRecentSlide()/formatDeletedSlide().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

$changedByColumn = ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))];

return [
    'active' => [
        'id' => 'osmium-carousel',
        'title' => 'Slides',
        'noun' => ['slide', 'slides'],
        'endpoint' => 'carousel/action/',
        'note' => 'Slides a theme can show in a carousel, in display order. A group name lets a site run more than one carousel. Scheduled slides appear and disappear by themselves.',
        'emptyText' => 'No slides yet - use Add Slide to create one.',
        'order' => null, // Server order: display order, then oldest first
        'columns' => [
            ['key' => 'thumbHtml', 'label' => '', 'type' => 'raw', 'sortable' => false, 'nowrap' => true],
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'groupName', 'label' => 'Group', 'type' => 'text', 'nowrap' => true],
            ['key' => 'statusBadge', 'label' => 'Status', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'sortOrder', 'label' => 'Order', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'event' => 'edit'],
            [
                'label' => 'Turn on',
                'icon' => 'bx-show',
                'action' => 'enable',
                'visible' => fn(array $row, array $context): bool => !$row['enabled'],
            ],
            [
                'label' => 'Turn off',
                'icon' => 'bx-hide',
                'action' => 'disable',
                'visible' => fn(array $row, array $context): bool => (bool) $row['enabled'],
            ],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this slide? It can be restored from Deleted Slides.',
                'danger' => true,
            ],
        ],
    ],

    'recent' => [
        'id' => 'osmium-carousel-recent',
        'title' => 'Recent Changes',
        'noun' => ['slide', 'slides'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'carousel/action/',
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            $changedByColumn,
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'updatedDate', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'deleted' => ListView::deletedPreset([
        'id' => 'osmium-carousel-deleted',
        'title' => 'Deleted Slides',
        'noun' => ['item', 'items'],
        'endpoint' => 'carousel/action/',
        'hideWhenEmpty' => false,
        'headerButton' => [
            'label' => 'Purge All',
            'icon' => 'bx-trash',
            'class' => 'btn-outline-danger',
            'action' => 'purge_deleted',
            'confirm' => 'Permanently delete all deleted slides? This cannot be undone!',
            'visible' => fn(array $rows, array $context): bool => (bool) $context['isAdmin'],
        ],
        'columns' => [
            $changedByColumn,
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'deletedDate', 'label' => 'Deleted', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ]),
];
