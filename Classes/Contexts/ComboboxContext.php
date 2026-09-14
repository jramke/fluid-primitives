<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Domain\Dto\ListCollectionItem;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasListCollectionTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class ComboboxContext extends AbstractComponentContext
{
    use HasListCollectionTrait;
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    /**
     * @return array<string>|null
     */
    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        // `defaultValue` is declared type="mixed" and genuinely accepts either shape checked below.
        // @mago-expect analysis:mixed-assignment
        $defaultValue = $this->get('defaultValue');

        if ($defaultValue === null || $defaultValue === '') {
            return null;
        }

        if (is_string($defaultValue)) {
            return [$defaultValue];
        }

        return is_array($defaultValue) ? array_map(Typed::string(...), $defaultValue) : null;
    }

    public function getInitialInputValue(): string
    {
        // `defaultInputValue` is declared type="mixed"; is_string() below rejects anything else.
        // @mago-expect analysis:mixed-assignment
        $defaultInputValue = $this->get('defaultInputValue');
        if (is_string($defaultInputValue) && $defaultInputValue !== '') {
            return $defaultInputValue;
        }

        if ($this->get('multiple')) {
            return '';
        }

        $collection = $this->getCollection();
        if (!$collection instanceof ListCollection) {
            return '';
        }

        $defaultValue = $this->getDefaultValue() ?? [];
        if ($defaultValue === []) {
            return '';
        }

        $selectionBehavior = Typed::stringOrNull($this->get('selectionBehavior')) ?: 'replace';
        if ($selectionBehavior === 'clear') {
            return '';
        }

        $selectedItems = $collection->findMany($defaultValue);

        if ($selectionBehavior === 'preserve') {
            return '';
        }

        return $collection->stringifyItems($selectedItems);
    }

    public function getItemState(ListCollectionItem|array $item): object
    {
        $defaultValue = $this->getDefaultValue() ?? [];
        $rootDisabled = Typed::bool($this->get('disabled'));
        $defaultHighlightedValue = Typed::stringOrNull($this->get('defaultHighlightedValue'));

        if ($item instanceof ListCollectionItem) {
            return (object)[
                'value' => $item->value,
                'selected' => in_array($item->value, $defaultValue, strict: true),
                'disabled' => $item->disabled ?: ($rootDisabled ?: null),
                'highlighted' => $defaultHighlightedValue === $item->value,
            ];
        }

        $collection = $this->getCollection();
        $value = $collection?->getItemValue($item) ?? '';
        $itemDisabled = $collection?->getItemDisabled($item) ?? false;

        return (object)[
            'value' => $value,
            'selected' => in_array($value, $defaultValue, strict: true),
            'disabled' => $itemDisabled ?: ($rootDisabled ?: null),
            'highlighted' => $defaultHighlightedValue === $value,
        ];
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'triggerLabel' => 'combobox.triggerLabel',
            'clearTriggerLabel' => 'combobox.clearTriggerLabel',
        ]);
    }
}
