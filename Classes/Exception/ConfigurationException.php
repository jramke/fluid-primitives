<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

/**
 * The project is set up in a way the library cannot work with: a missing package, an unknown component collection, a bad target in a CLI command.
 */
class ConfigurationException extends \RuntimeException implements ExceptionInterface {}
