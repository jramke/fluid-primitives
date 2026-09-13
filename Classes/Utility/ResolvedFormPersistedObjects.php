<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;

/**
 * A form's bound object and its nested persisted sub-objects, as resolved by
 * {@see ExtbasePersistedObjectResolver}.
 */
final readonly class ResolvedFormPersistedObjects
{
    /**
     * @param array<string, AbstractDomainObject> $nestedObjects
     */
    public function __construct(
        public ?AbstractDomainObject $boundObject,
        public array $nestedObjects,
    ) {}
}
