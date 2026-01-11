<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;

class NumberInputContext extends AbstractComponentContext
{
    #[ExposeToClient]
    public function getDefaultValue(): mixed
    {
        return (string)$this->get('defaultValue');
    }

    #[ExposeToClient]
    public function getLocale(): ?string
    {
        if ($this->has('locale')) {
            return (string)$this->get('locale');
        }
        $language = $this->getRenderingContext()->getRequest()->getAttribute('language');
        $locale = (string)$language->getLocale() ?? null;
        return $locale;
    }

    public function getFormattedValue(): string
    {
        $value = $this->get('defaultValue');
        if ($value === null || $value === '') {
            return '';
        }

        // For server-side rendering, we just return the raw value
        // The client will format it according to formatOptions
        return (string)$value;
    }

    public function getCanDecrement(): bool
    {
        $value = $this->get('defaultValue');
        $min = $this->get('min');

        if (empty($value) || $min === null) {
            return true;
        }

        return (float)$value > (float)$min;
    }

    public function getCanIncrement(): bool
    {
        $value = $this->get('defaultValue');
        $max = $this->get('max');

        if (empty($value) || $max === null) {
            return true;
        }

        return (float)$value < (float)$max;
    }

    public function getDataAttributes(): array
    {
        return [
            'disabled' => $this->get('disabled') ?? null,
            'invalid' => $this->get('invalid') ?? null,
            'readonly' => $this->get('readOnly') ?? null,
        ];
    }
}
