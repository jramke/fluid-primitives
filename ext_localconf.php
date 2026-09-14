<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Component\ComponentPrimitivesCollection;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// The standard TYPO3 extension bootstrap guard: aborts if this file is somehow included outside a
// TYPO3 request. Its value is intentionally discarded - die() never returns, so there's nothing to do
// with the left-hand side once it's reached.
// @mago-expect analysis:unused-statement
defined('TYPO3') || die();

// ext_localconf.php is TYPO3's own bootstrap convention for registering Fluid namespaces and
// extension configuration - $GLOBALS['TYPO3_CONF_VARS'] is the only API for it, there is no
// DI-injectable alternative at this stage of the framework's bootstrap.

// Make ui a global namespace
// @mago-expect lint:no-global,no-isset
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'])) {
    // $GLOBALS['TYPO3_CONF_VARS'] is untyped, so every nesting level of this write is flagged
    // separately - inherent to it being TYPO3's own global configuration array.
    // @mago-expect lint:no-global
    // @mago-expect analysis:mixed-array-assignment
    // @mago-expect analysis:mixed-array-assignment
    // @mago-expect analysis:mixed-array-assignment
    // @mago-expect analysis:mixed-array-assignment
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'] = [];
}
// @mago-expect lint:no-global
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'][] = 'Jramke\\FluidPrimitives\\ViewHelpers';

// Register primitives namespace
// @mago-expect lint:no-global
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
// @mago-expect analysis:mixed-array-assignment
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['primitives'] = [
    ComponentPrimitivesCollection::class,
];

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
