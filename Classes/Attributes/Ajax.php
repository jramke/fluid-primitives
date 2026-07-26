<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Attributes;

use Attribute;

/**
 * Marks a method to be exposed as an AJAX endpoint for the component.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Ajax
{
    public function __construct() {}
}
