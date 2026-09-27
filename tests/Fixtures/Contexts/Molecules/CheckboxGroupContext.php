<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures\Contexts\Molecules;

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;

/**
 * Stands in for a tiered atomic-design component's context class, resolved via a namespace
 * mirroring its own template folder ("Molecules/CheckboxGroup/Root.fluid.html").
 */
final class CheckboxGroupContext extends AbstractComponentContext {}
