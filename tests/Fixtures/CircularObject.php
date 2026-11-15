<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * A plain object that cannot be encoded as JSON: it refers to itself.
 */
final class CircularObject
{
    public self $self;

    public function __construct()
    {
        $this->self = $this;
    }
}
