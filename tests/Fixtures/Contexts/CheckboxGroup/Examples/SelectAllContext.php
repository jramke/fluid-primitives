<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures\Contexts\CheckboxGroup\Examples;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;

/**
 * Stands in for a nested example/demo component's own context class, resolved via a namespace
 * mirroring its own template folder ("CheckboxGroup/Examples/SelectAll.fluid.html") - root only via
 * the folder-shape default, never via the classic single-segment/".root" shape.
 */
final class SelectAllContext extends AbstractComponentContext {}
