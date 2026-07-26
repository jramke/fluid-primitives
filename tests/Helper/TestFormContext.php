<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Helper;

use Jramke\FluidPrimitives\Contexts\FormContext;

final class TestFormContext extends FormContext
{
    public function exposePrefixFieldName(string $fieldName, ?string $objectName = null): string
    {
        return $this->prefixFieldName($fieldName, $objectName);
    }

    public function setTestFieldNamePrefix(string $fieldNamePrefix): void
    {
        $this->set('testFieldNamePrefix', $fieldNamePrefix);
    }

    #[\Override]
    public function getFieldNamePrefix(): string
    {
        return (string)($this->get('testFieldNamePrefix') ?? '');
    }
}
