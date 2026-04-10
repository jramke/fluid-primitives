<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class NavigationMenuContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string, disabled?:bool|null } $item
     */
    public function getItemState(array $item): object
    {
        $value = $item['value'] ?? null;
        $disabled = $item['disabled'] ?? null;

        $defaultValue = $this->get('defaultValue') ?? '';
        $isOpen = $defaultValue !== '' && $defaultValue === $value;

        return (object)[
            'open' => $isOpen,
            'disabled' => $disabled,
            'state' => $isOpen ? 'open' : 'closed',
        ];
    }

    public function getGlobalState(): object
    {
        $defaultValue = $this->get('defaultValue') ?? '';
        $isOpen = $defaultValue !== '';

        return (object)[
            'open' => $isOpen,
            'state' => $isOpen ? 'open' : 'closed',
        ];
    }

    public function setIsInsideContent(): void
    {
        $this->set('isInsideContent', true);
    }

    public function unsetIsInsideContent(): void
    {
        $this->set('isInsideContent', false);
    }
}
