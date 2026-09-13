<?php

declare(strict_types=1);

// ext_emconf.php is TYPO3's own bootstrap convention for extension metadata: the extension loader
// includes this file after predefining $_EXTKEY and reading $EM_CONF back out, so both are always
// bound by the including scope - there is no declaration for either in this file itself.
// @mago-expect analysis:undefined-variable
// @mago-expect analysis:undefined-variable
// @mago-expect analysis:mixed-array-assignment
$EM_CONF[$_EXTKEY] = [
    'title' => 'Fluid Primitives',
    'description' => 'The headless component library for TYPO3 Fluid',
    'version' => '0.19.1',
    'state' => 'beta',
    'author' => 'Joost Ramke',
    'author_email' => 'hey@joostramke.com',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.3.99',
        ],
    ],
    'autoload' => [
        'psr-4' => ['Jramke\\FluidPrimitives\\' => 'Classes'],
    ],
];
