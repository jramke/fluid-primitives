<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Attributes;

use Attribute;

/**
 * Marks a method to be exposed to the client hydration data.
 *
 * This attribute should only be used on methods in classes extending AbstractComponentContext.
 * The method must be public and have no required parameters.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ExposeToClient
{
    public function __construct(
        public ?string $name = null,
        public bool $excludeIfNull = false
    ) {}
}
