<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class NumberInputContext extends AbstractComponentContext
{
    public function __construct(
        protected readonly TranslatorService $translator,
    ) {}

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): mixed
    {
        return (string)$this->get('defaultValue');
    }

    #[ExposeToClient]
    public function getLocale(): ?string
    {
        return $this->translator->getLocale($this->getRequest());
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [
            'incrementLabel' => $this->translator->translate('numberInput.incrementLabel', $this->getRequest()),
            'decrementLabel' => $this->translator->translate('numberInput.decrementLabel', $this->getRequest()),
        ];

        return array_merge($defaults, $overrides);
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
