<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.title',
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
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,required',
            ],
        ],
        'category' => [
            'exclude' => true,
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.category.statistics', 'statistics'],
                    ['LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.category.marketing', 'marketing'],
                ],
                'default' => 'marketing',
            ],
        ],
        'whitelist' => [
            'exclude' => true,
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.whitelist',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'blacklist' => [
            'exclude' => true,
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.blacklist',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'domains' => [
            'exclude' => true,
            'label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.domains',
            'description' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.serviceRules.field.domains.description',
            'config' => [
                'type' => 'text',
                'rows' => 6,
                'eval' => 'trim,required',
            ],
        ],
    ],
];
