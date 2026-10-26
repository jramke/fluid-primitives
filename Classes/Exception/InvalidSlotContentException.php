<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * The slot content of a component has a shape the component cannot work with, e.g. `asChild` without exactly one child element.
 */
class InvalidSlotContentException extends Exception implements ExceptionInterface {}
