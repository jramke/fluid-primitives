<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * A part or ViewHelper was used outside the component it belongs to, so there is no context to attach to.
 */
class MissingContextException extends Exception implements ExceptionInterface {}
