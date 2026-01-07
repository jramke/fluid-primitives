<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

// Make ui a global namespace
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'][] = 'Jramke\\FluidPrimitives\\ViewHelpers';

// Register primitives namespace
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['primitives'] = ['Jramke\\FluidPrimitives\\Component\\ComponentPrimitivesCollection'];

// Exclude specific arguments from storybook controls when using EXT:storybook
if (ExtensionManagementUtility::isLoaded('storybook')) {
    $existing = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] ?? '';
    $existingArr = GeneralUtility::trimExplode(',', $existing, true);

    $addList = ['ids', 'attributes', 'asChild', 'rootId', 'controlled', 'spreadProps'];

    $merged = array_values(array_unique(array_merge($existingArr, $addList)));

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] = implode(',', $merged);
}
