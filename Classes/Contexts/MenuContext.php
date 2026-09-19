<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;

class MenuContext extends AbstractComponentContext
{
    /**
     * State for a `menu.trigger`/`menu.contextTrigger`, derived from the root's `defaultOpen`/
     * `defaultTriggerValue` - the same values a menu with a single trigger (`$value === null`)
     * or multiple triggers (`$value` identifies this one) will also compute client-side once
     * hydrated, so first paint doesn't flash a wrong `aria-expanded`/`data-state`.
     */
    public function getTriggerState(?string $value = null): object
    {
        $open = Typed::bool($this->get('defaultOpen'));
        $current = $value !== null && $this->get('defaultTriggerValue') === $value;

        return (object)[
            'expanded' => $value === null ? $open : $open && $current,
            'current' => $current,
            'state' => $open ? 'open' : 'closed',
        ];
    }

    /**
     * @param array{ value?:string|null, valueText?:string|null, type?:string|null, disabled?:bool|null, checked?:bool|null } $itemProps
     */
    public function getOptionItemState(array $itemProps): object
    {
        $type = $itemProps['type'] ?? 'checkbox';
        $checked = $itemProps['checked'] ?? false;

        return (object)[
            'value' => $itemProps['value'] ?? null,
            'valueText' => $itemProps['valueText'] ?? null,
            'type' => $type,
            'disabled' => $itemProps['disabled'] ?? null,
            'checked' => $checked,
            'role' => 'menuitem' . $type,
            'state' => $checked ? 'checked' : 'unchecked',
        ];
    }
}
