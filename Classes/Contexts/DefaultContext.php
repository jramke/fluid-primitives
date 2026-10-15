<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

/**
 * The context of a root component without a `<Name>Context` class of its own: the renderer needs a
 * concrete object, and this one carries no data. Not a parent class - project wide data belongs in the
 * global context, data of one component in its own `<Name>Context`.
 *
 * @internal
 */
final class DefaultContext extends AbstractComponentContext {}
