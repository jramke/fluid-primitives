<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * A second `<component>.provider` uses a `rootId` that a provider of the same component already registered.
 */
class DuplicateProviderException extends Exception implements ExceptionInterface {}
