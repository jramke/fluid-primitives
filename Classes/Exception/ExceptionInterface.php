<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

/**
 * Marker for everything Fluid Primitives throws: `catch (ExceptionInterface $exception)` takes all of it.
 */
interface ExceptionInterface extends \Throwable {}
