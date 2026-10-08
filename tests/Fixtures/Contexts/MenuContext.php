<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures\Contexts;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;

/**
 * Stands in for an unrelated, real, flat-namespaced context class (like the real
 * `Jramke\FluidPrimitives\Contexts\MenuContext`) that a folder-shape-only root component with the
 * same last-segment name (e.g. "Icon/Menu") must never resolve to.
 */
final class MenuContext extends AbstractComponentContext {}
