<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;

class ToggleGroupContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string, disabled?:bool|null } $item
     */
    public function getItemState(array $item): object
    {
        $defaultValue = (array)($this->get('defaultValue') ?? []);

        return (object)[
            'pressed' => in_array($item['value'], $defaultValue, strict: true),
            // like Zag, a disabled group disables every item, an item can't opt out
            'disabled' => ($item['disabled'] ?? false) || Typed::bool($this->get('disabled')),
        ];
    }
}
