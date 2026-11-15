<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * A plain object without `JsonSerializable`, like a small DTO of a project.
 */
final class PlainObject
{
    public function __construct(
        public string $value = 'shipped',
    ) {}
}
