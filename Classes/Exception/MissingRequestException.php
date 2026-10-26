<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Exception;

/**
 * A render has no request, or not an Extbase one, where the code needs it.
 */
class MissingRequestException extends MissingContextException {}
