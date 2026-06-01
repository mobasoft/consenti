<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'Consenti Service Rules',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,domains',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-element-text.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'hidden, title, category, whitelist, blacklist, domains',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'Hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'title' => [
            'exclude' => true,
            'label' => 'Title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,required',
            ],
        ],
        'category' => [
            'exclude' => true,
            'label' => 'Category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['Statistics', 'statistics'],
                    ['Marketing', 'marketing'],
                ],
                'default' => 'marketing',
            ],
        ],
        'whitelist' => [
            'exclude' => true,
            'label' => 'Whitelist (always allow matching domains)',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'blacklist' => [
            'exclude' => true,
            'label' => 'Blacklist (never allow matching domains)',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'domains' => [
            'exclude' => true,
            'label' => 'Domains',
            'description' => 'One or multiple domains (comma, whitespace, or newline separated), e.g. youtube.com',
            'config' => [
                'type' => 'text',
                'rows' => 6,
                'eval' => 'trim,required',
            ],
        ],
    ],
];
