<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives;

class Constants
{
    public const PROP_ROOT_ID = 'rootId';

    public const PROPS_MARKED_FOR_CLIENT_KEY = '#__propsMarkedForClient';
    public const PROPS_MARKED_FOR_CONTEXT_KEY = '#__propsMarkedForContext';

    public const RESERVED_PROPS = [
        self::PROP_ROOT_ID, // reserved as we declare it manually
        'context', // reserved for the component context
        'component', // reserved for the component data
        'settings', // reserved for the component settings
        'class', // reserved for the component class and added automatically for every component
        'asChild',
    ];
}
