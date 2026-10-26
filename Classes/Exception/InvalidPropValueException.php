<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

/**
 * A prop or ViewHelper argument has a value that is not allowed, or is used where it cannot be: an unknown option, a wrong type, a prop that is not forwardable.
 */
class InvalidPropValueException extends InvalidArgumentValueException implements ExceptionInterface {}
