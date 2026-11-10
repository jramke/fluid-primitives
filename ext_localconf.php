<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// The standard TYPO3 extension bootstrap guard: aborts if this file is somehow included outside a
// TYPO3 request. Its value is intentionally discarded - die() never returns, so there's nothing to do
// with the left-hand side once it's reached.
// @mago-expect analysis:unused-statement
defined('TYPO3') || die();

// Exclude specific arguments from storybook controls when using EXT:storybook
if (ExtensionManagementUtility::isLoaded('storybook')) {
    // @mago-expect lint:no-global
    $existing = Typed::string($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] ?? null);
    $existingArr = GeneralUtility::trimExplode(',', $existing, true);

    $globalPropsWithoutClass = array_filter(Constants::GLOBAL_PROPS, static fn($value) => $value !== 'class');

    $merged = array_values(array_unique(array_merge($existingArr, $globalPropsWithoutClass)));

    // @mago-expect lint:no-global
    // @mago-expect analysis:mixed-array-assignment
    // @mago-expect analysis:mixed-array-assignment
    // @mago-expect analysis:mixed-array-assignment
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['storybook']['excludeArguments'] = implode(',', $merged);
}
