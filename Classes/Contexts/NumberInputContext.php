<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;

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

    /**
     * The value as a number, `null` for an empty input. Zag takes a number, and a template can pass
     * the value as text, as a form field keeps it.
     */
    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?float
    {
        $value = Typed::stringOrNull($this->get('defaultValue'));

        return $value === null || trim($value) === '' || !is_numeric(trim($value)) ? null : (float)trim($value);
    }

    // excludeIfNull: no locale override should mean "key absent" (Zag falls back to its own
    // default), not a `null` sent over the wire - @zag-js/number-input passes `locale` straight
    // into `new Intl.NumberFormat(locale, ...)` with no nullish handling of its own, and a literal
    // `null` there is not the same as an absent/undefined argument.
    #[ExposeToClient(excludeIfNull: true)]
    public function getLocale(): ?string
    {
        return Typed::stringOrNull($this->get('locale')) ?: Typed::stringOrNull($this->getGlobals()->get('locale'));
    }

    #[ExposeToClient]
    /**
     * @return array{incrementLabel: string, decrementLabel: string, roleDescription: string}
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'incrementLabel' => 'numberInput.incrementLabel',
            'decrementLabel' => 'numberInput.decrementLabel',
            'roleDescription' => 'numberInput.roleDescription',
        ]);
    }

    // `f:if` treats a `0` as false, so the template cannot ask for the value, `min` or `max` itself
    public function getHasDefaultValue(): bool
    {
        return $this->getDefaultValue() !== null;
    }

    public function getHasMin(): bool
    {
        return Typed::floatOrNull($this->get('min')) !== null;
    }

    public function getHasMax(): bool
    {
        return Typed::floatOrNull($this->get('max')) !== null;
    }

    // unformatted: the client formats it with `formatOptions` on its first render
    public function getFormattedValue(): string
    {
        $value = $this->getDefaultValue();

        return $value === null ? '' : Typed::string($value);
    }

    public function getCanDecrement(): bool
    {
        $value = $this->getDefaultValue();
        $min = Typed::floatOrNull($this->get('min'));

        return $value === null || $min === null || $value > $min;
    }

    public function getCanIncrement(): bool
    {
        $value = $this->getDefaultValue();
        $max = Typed::floatOrNull($this->get('max'));

        return $value === null || $max === null || $value < $max;
    }
}
