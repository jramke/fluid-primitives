<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

/**
 * A root whose `rootId` has a `<component>.provider` gets a prop of its own. Its props belong on the provider.
 */
class ProvidedRootArgumentException extends InvalidArgumentValueException implements ExceptionInterface {}
