<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;

class AccordionContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string, disabled?:bool|null } $item
     */
    public function getItemState(array $item): object
    {
        $value = $item['value'];
        $disabled = $item['disabled'] ?? null;

        $defaultValue = (array)($this->get('defaultValue') ?? []);
        $rootDisabled = Typed::bool($this->get('disabled'));

        return (object)[
            'expanded' => in_array($value, $defaultValue, strict: true),
            'disabled' => $disabled ?? $rootDisabled, // null if not set so it can be directly uses as `data-disabled` by the TagAttributes class
        ];
    }
}
