<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\UnresolvableViewHelperException;

/**
 * A component is used in a template but its template file does not exist.
 */
class UnresolvableComponentException extends UnresolvableViewHelperException implements ExceptionInterface {}
