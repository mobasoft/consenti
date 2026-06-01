<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.title',
        'label' => 'date_key',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'date_key,revision',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-element-table.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'hidden, date_key, revision, necessary, statistics, marketing, hits, first_seen, last_seen',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0],
        ],
        'date_key' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.dateKey', 'config' => ['type' => 'input', 'readOnly' => true]],
        'revision' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.revision', 'config' => ['type' => 'input', 'readOnly' => true]],
        'necessary' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.necessary', 'config' => ['type' => 'number', 'readOnly' => true]],
        'statistics' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.statistics', 'config' => ['type' => 'number', 'readOnly' => true]],
        'marketing' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.marketing', 'config' => ['type' => 'number', 'readOnly' => true]],
        'hits' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.hits', 'config' => ['type' => 'number', 'readOnly' => true]],
        'first_seen' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.firstSeen', 'config' => ['type' => 'datetime', 'readOnly' => true]],
        'last_seen' => ['label' => 'LLL:EXT:consenti/Resources/Private/Language/locallang.xlf:backend.consentStat.field.lastSeen', 'config' => ['type' => 'datetime', 'readOnly' => true]],
    ],
];
