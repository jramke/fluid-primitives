<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;

class FieldsetContext extends AbstractComponentContext
{
    /**
     * The state every part renders as data attributes on the server. A fieldset nested in a
     * disabled one learns about it on the client only (it tracks its ancestors itself).
     *
     * @return array{disabled: bool, invalid: bool}
     */
    public function getDataAttributes(): array
    {
        return [
            'disabled' => Typed::bool($this->get('disabled')),
            'invalid' => Typed::bool($this->get('invalid')),
        ];
    }

    public function isErrorTextHidden(): bool
    {
        return !Typed::bool($this->get('invalid'));
    }
}
