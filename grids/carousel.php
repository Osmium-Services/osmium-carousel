<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Slides list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/carousel.php but nothing is shared with it. The actions are a copy of the list's for now.
 * Cards follow the server's display order.
 */
return [
    'columns' => 4,
    'imageHeight' => 120,
    'image' => ['key' => 'thumbUrl', 'type' => 'image', 'altKey' => 'title'],
    'title' => ['key' => 'title', 'type' => 'text', 'strong' => true],
    'meta' => [
        ['key' => 'statusBadge', 'type' => 'raw'],
        ['key' => 'groupName', 'type' => 'text', 'small' => true],
        ['key' => 'sortOrder', 'type' => 'text', 'small' => true],
    ],
    'actionsAt' => 'bottom-end',
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
];
