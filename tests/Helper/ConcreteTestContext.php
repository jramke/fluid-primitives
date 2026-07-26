<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Helper;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;

/**
 * Minimal concrete context for unit testing AbstractComponentContext behavior
 * (get() with dot notation, ArrayAccess, etc.) without requiring full initialization
 * dependencies in every test setup.
 */
final class ConcreteTestContext extends AbstractComponentContext {}
