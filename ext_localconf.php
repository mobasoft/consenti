<?php

declare(strict_types=1);

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    "@import 'EXT:consenti/Configuration/TypoScript/setup.typoscript'"
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptConstants(
    "@import 'EXT:consenti/Configuration/TypoScript/constants.typoscript'"
);
