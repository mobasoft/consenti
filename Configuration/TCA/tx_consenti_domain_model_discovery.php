<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.title',
        'label' => 'host',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'host,category,source_type,last_source_url,decision',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-element-table.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'hidden, host, category, source_type, decision, hits, first_seen, last_seen, last_source_url',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'host' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.host',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'category' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.category',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'source_type' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.sourceType',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'decision' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.decision',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'hits' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.hits',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'readOnly' => true,
            ],
        ],
        'first_seen' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.firstSeen',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'last_seen' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.lastSeen',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'last_source_url' => [
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.discovery.field.lastSourceUrl',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'readOnly' => true,
            ],
        ],
    ],
];
