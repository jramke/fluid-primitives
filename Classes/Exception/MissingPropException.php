<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\MissingArgumentException;

/**
 * A required prop or ViewHelper argument was not provided.
 */
class MissingPropException extends MissingArgumentException implements ExceptionInterface {}
