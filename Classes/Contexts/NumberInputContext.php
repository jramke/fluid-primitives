<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
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
        return Typed::string($this->get('defaultValue'));
    }

    // excludeIfNull: no locale override should mean "key absent" (Zag falls back to its own
    // default), not a `null` sent over the wire - @zag-js/number-input passes `locale` straight
    // into `new Intl.NumberFormat(locale, ...)` with no nullish handling of its own, and a literal
    // `null` there is not the same as an absent/undefined argument.
    #[ExposeToClient(excludeIfNull: true)]
    public function getLocale(): ?string
    {
        return $this->translator->getLocale($this->getRequest());
    }

    #[ExposeToClient]
    /**
     * @return array{incrementLabel: string|false, decrementLabel: string|false}
     */
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
        $min = Typed::floatOrNull($this->get('min'));

        if ($value === '' || $min === null) {
            return true;
        }

        return (float)$value > $min;
    }

    public function getCanIncrement(): bool
    {
        $value = $this->getDefaultValue();
        $max = Typed::floatOrNull($this->get('max'));

        if ($value === '' || $max === null) {
            return true;
        }

        return (float)$value < $max;
    }

    public function getDataAttributes(): array
    {
        return [
            'disabled' => Typed::boolOrNull($this->get('disabled')),
            'invalid' => Typed::boolOrNull($this->get('invalid')),
            'readonly' => Typed::boolOrNull($this->get('readOnly')),
        ];
    }
}
