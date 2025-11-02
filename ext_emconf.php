<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fluid Primitives',
    'description' => 'The headless component library for TYPO3 Fluid',
    'version' => '0.5.1',
    'state' => 'beta',
    'author' => 'Joost Ramke',
    'author_email' => 'hey@joostramke.com',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.3.99',
            'typo3' => '13.4.0-13.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => ['Jramke\\FluidPrimitives\\' => 'Classes']
    ],
];
