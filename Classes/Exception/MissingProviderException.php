<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * A part or `ui:context` names a `rootId` that no `<component>.provider` rendered before it registered.
 */
class MissingProviderException extends Exception implements ExceptionInterface {}
