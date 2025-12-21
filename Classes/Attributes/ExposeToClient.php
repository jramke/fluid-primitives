<?php

namespace Jramke\FluidPrimitives\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ExposeToClient
{
    public function __construct(
        public ?string $name = null
    ) {}
}
