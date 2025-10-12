<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fluid Primitives',
    'description' => 'Build Component Compositions in Fluid.',
    'version' => '0.0.1',
    'state' => 'alpha',
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
