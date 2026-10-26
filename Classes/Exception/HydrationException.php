<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * A component cannot be hydrated on the client: no rootId, a duplicate rootId, a prop value that cannot be sent as JSON.
 */
class HydrationException extends Exception implements ExceptionInterface {}
