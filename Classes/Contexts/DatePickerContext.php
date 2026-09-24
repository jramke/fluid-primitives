<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class DatePickerContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    public function getState(): string
    {
        return $this->get('defaultOpen') ? 'open' : 'closed';
    }

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        // `defaultValue` is declared type="mixed" and genuinely accepts either shape checked below.
        // @mago-expect analysis:mixed-assignment
        $defaultValue = $this->get('defaultValue');
        if ($defaultValue === null || $defaultValue === []) {
            return null;
        }

        if (is_string($defaultValue)) {
            return [$defaultValue];
        }

        return Typed::arrayOrNull($defaultValue);
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'clearTriggerLabel' => 'date-picker.clearTriggerLabel',
            'monthSelectLabel' => 'date-picker.monthSelectLabel',
            'yearSelectLabel' => 'date-picker.yearSelectLabel',
            'contentLabel' => 'date-picker.contentLabel',
            'weekColumnHeaderLabel' => 'date-picker.weekColumnHeaderLabel',
        ]);
    }
}
