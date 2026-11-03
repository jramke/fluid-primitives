<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * A template is named `Provider` in a folder that holds a root component, where that name is the root's provider.
 */
class ReservedComponentNameException extends Exception implements ExceptionInterface {}
