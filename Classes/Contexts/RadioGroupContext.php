<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

class RadioGroupContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string, disabled?:bool|null, invalid?:bool|null } $itemProps
     */
    public function getItemDataAttributes(array $itemProps): array
    {
        $itemState = $this->getItemState($itemProps);

        return [
            'disabled' => $itemState['disabled'],
            'readonly' => $this->get('readOnly') ?? null,
            'invalid' => $itemState['invalid'],
            'state' => $itemState['checked'] ? 'checked' : 'unchecked',
            'orientation' => $this->get('orientation') ?? null,
            'value' => $itemState['value'] ?? null,
        ];
    }

    /**
     * @param array{ value:string, disabled?:bool|null, invalid?:bool|null } $itemProps
     */
    public function getItemState(array $itemProps): array
    {
        return [
            'value' => $itemProps['value'] ?? null,
            'disabled' => $itemProps['disabled'] ?? $this->get('disabled') ?? null,
            'invalid' => $itemProps['invalid'] ?? $this->get('invalid') ?? null,
            'checked' => $this->get('defaultValue') === ($itemProps['value'] ?? null),
        ];
    }
}
