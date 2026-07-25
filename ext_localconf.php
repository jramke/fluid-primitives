<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Controller\AjaxDispatcherController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

// Make ui a global namespace
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'][] = 'Jramke\\FluidPrimitives\\ViewHelpers';

// Register primitives namespace
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['primitives'] = [
    ComponentPrimitivesCollection::class,
];

// Exclude specific arguments from storybook controls when using EXT:storybook
if (ExtensionManagementUtility::isLoaded('storybook')) {
    $existing = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] ?? '';
    $existingArr = GeneralUtility::trimExplode(',', $existing, true);

    $globalPropsWithoutClass = array_filter(Constants::GLOBAL_PROPS, static fn($value) => $value !== 'class');

    $merged = array_values(array_unique(array_merge($existingArr, $globalPropsWithoutClass)));

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] = implode(',', $merged);
}

ExtensionUtility::configurePlugin(
    'FluidPrimitives',
    'AjaxDispatcher',
    [AjaxDispatcherController::class => 'dispatch'],
    [AjaxDispatcherController::class => 'dispatch'],
);
