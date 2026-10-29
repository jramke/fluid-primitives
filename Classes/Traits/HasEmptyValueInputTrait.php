<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Utility\Typed;

/**
 * Shared by the Context classes of a form control that submits nothing while it is empty (an unchecked
 * checkbox, a combobox without a selection, ...). Its template renders `<ui:emptyValueInput />`, a hidden
 * input with the value `''`, so the name is always submitted and Extbase can clear the property. It
 * drops the `[]` of a multi-value name, as core Fluid's own hidden fields do: Extbase maps `''` to an
 * empty array or ObjectStorage, `['']` would hold one empty entry.
 */
trait HasEmptyValueInputTrait
{
    abstract public function get(string $key): mixed;

    /**
     * The name the empty-value input submits under: the field name without a trailing `[]`, or `null`
     * when the control has no name or submits `''` by itself.
     */
    public function getEmptyValueName(): ?string
    {
        $name = Typed::stringOrNull($this->get('name'));
        if ($name === null || $name === '' || !$this->rendersEmptyValueInput()) {
            return null;
        }

        return str_ends_with($name, '[]') ? substr($name, offset: 0, length: -2) : $name;
    }

    /**
     * Whether the control needs an empty-value input at all. A context overrides it where the control
     * submits `''` itself, like a single select does through its placeholder option.
     */
    protected function rendersEmptyValueInput(): bool
    {
        return true;
    }
}
