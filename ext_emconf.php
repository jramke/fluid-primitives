<?php

declare(strict_types=1);

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
