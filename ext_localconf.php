<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

// Make ui a global namespace
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'] = [];
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui'][] = 'Jramke\\FluidPrimitives\\ViewHelpers';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['primitives'] = ['Jramke\\FluidPrimitives\\Component\\ComponentPrimitivesCollection'];
