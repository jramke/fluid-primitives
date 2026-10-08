<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Enum\PopupType;
use Jramke\FluidPrimitives\Utility\EnumUtility;

/**
 * Shared by Context classes of components with a `popupType` prop (Select, Combobox), so templates
 * can mirror what Zag renders for the popup on the server: the trigger's `aria-haspopup` and the
 * content's `role`. Requires the using class to have a `get(string $key): mixed` method.
 */
trait HasPopupTypeTrait
{
    abstract public function get(string $key): mixed;

    public function isDialogPopup(): bool
    {
        return EnumUtility::normalize($this->get('popupType')) === PopupType::Dialog->value;
    }
}
