<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Domain\Model\ListCollectionItem;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class ComboboxContext extends AbstractComponentContext
{
    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        $defaultValue = $this->get('defaultValue');

        if ($defaultValue === null || $defaultValue === '') {
            return null;
        }

        if (is_string($defaultValue)) {
            return [$defaultValue];
        }

        return is_array($defaultValue) ? $defaultValue : null;
    }

    public function getInitialInputValue(): string
    {
        $defaultInputValue = $this->get('defaultInputValue');
        if (is_string($defaultInputValue) && $defaultInputValue !== '') {
            return $defaultInputValue;
        }

        if ($this->get('multiple')) {
            return '';
        }

        $collection = $this->getCollection();
        if (!$collection) {
            return '';
        }

        $defaultValue = $this->getDefaultValue() ?? [];
        if ($defaultValue === []) {
            return '';
        }

        $selectionBehavior = $this->get('selectionBehavior') ?: 'replace';
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
        $rootDisabled = $this->get('disabled') ?? false;
        $defaultHighlightedValue = $this->get('defaultHighlightedValue');

        if ($item instanceof ListCollectionItem) {
            return (object)[
                'value' => $item->value,
                'selected' => in_array($item->value, $defaultValue, true),
                'disabled' => $item->disabled ?: ($rootDisabled ?: null),
                'highlighted' => $defaultHighlightedValue === $item->value,
            ];
        }

        $collection = $this->getCollection();
        $value = $collection?->getItemValue($item) ?? '';
        $itemDisabled = $collection?->getItemDisabled($item) ?? false;

        return (object)[
            'value' => $value,
            'selected' => in_array($value, $defaultValue, true),
            'disabled' => $itemDisabled ?: ($rootDisabled ?: null),
            'highlighted' => $defaultHighlightedValue === $value,
        ];
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [
            'triggerLabel' => $this->translator->translate('combobox.triggerLabel', $this->getRequest()),
            'clearTriggerLabel' => $this->translator->translate('combobox.clearTriggerLabel', $this->getRequest()),
        ];

        return array_merge($defaults, $overrides);
    }

    public function getCollection(): ?ListCollection
    {
        $collection = $this->get('collection');
        return $collection instanceof ListCollection ? $collection : null;
    }
}
