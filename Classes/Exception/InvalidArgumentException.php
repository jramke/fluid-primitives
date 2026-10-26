<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

/**
 * A value passed to code without a Fluid dependency (a DTO, a registry) is not valid. The code that calls it in a render can translate it into an `InvalidPropValueException`.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
