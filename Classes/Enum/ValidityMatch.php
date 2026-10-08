<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Enum;

/**
 * The failed native constraint (a ValidityState flag) an error text is narrowed to.
 */
enum ValidityMatch: string
{
    case BadInput = 'badInput';
    case CustomError = 'customError';
    case PatternMismatch = 'patternMismatch';
    case RangeOverflow = 'rangeOverflow';
    case RangeUnderflow = 'rangeUnderflow';
    case StepMismatch = 'stepMismatch';
    case TooLong = 'tooLong';
    case TooShort = 'tooShort';
    case TypeMismatch = 'typeMismatch';
    case ValueMissing = 'valueMissing';
}
