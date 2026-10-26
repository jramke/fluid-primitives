<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Thrown by `ui:error`: a template author stops the render on purpose.
 */
class TemplateErrorException extends Exception implements ExceptionInterface {}
