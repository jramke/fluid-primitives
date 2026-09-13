<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class NumberInputContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        protected readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    #[ExposeToClient]
    public function getDefaultValue(): string
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
        return $this->translationsWithDefaults([
            'incrementLabel' => 'numberInput.incrementLabel',
            'decrementLabel' => 'numberInput.decrementLabel',
        ]);
    }

    public function getFormattedValue(): string
    {
        // For server-side rendering, we just return the raw value
        // The client will format it according to formatOptions
        return $this->getDefaultValue();
    }

    public function getCanDecrement(): bool
    {
        $value = $this->getDefaultValue();
        $min = $this->get('min');

        if ($value === '' || $min === null) {
            return true;
        }

        return (float)$value > (float)$min;
    }

    public function getCanIncrement(): bool
    {
        $value = $this->getDefaultValue();
        $max = $this->get('max');

        if ($value === '' || $max === null) {
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
