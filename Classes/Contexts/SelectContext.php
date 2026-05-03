<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Domain\Model\ListCollection;
use Jramke\FluidPrimitives\Domain\Model\ListCollectionItem;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class SelectContext extends AbstractComponentContext
{
    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        if (empty($this->get('defaultValue'))) {
            return null;
        }

        if (is_string($this->get('defaultValue'))) {
            return [$this->get('defaultValue')];
        }

        return $this->get('defaultValue');
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [
            'clearTriggerLabel' => $this->translator->translate('select.clearTriggerLabel', $this->getRequest()),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Get the state of a select item (selected, disabled).
     *
     * @param ListCollectionItem|array $item The item to get state for
     * @return object Object with 'selected' and 'disabled' properties
     */
    public function getItemState(ListCollectionItem|array $item): object
    {
        $defaultValue = $this->getDefaultValue() ?? [];
        $rootDisabled = $this->get('disabled') ?? false;

        // Handle ListCollectionItem objects directly
        if ($item instanceof ListCollectionItem) {
            return (object)[
                'selected' => in_array($item->value, $defaultValue, true),
                'disabled' => $item->disabled ?: ($rootDisabled ?: null),
            ];
        }

        // Fallback for raw items
        $collection = $this->getCollection();
        $value = $collection?->getItemValue($item);
        $itemDisabled = $collection?->getItemDisabled($item) ?? false;

        return (object)[
            'selected' => in_array($value, $defaultValue, true),
            'disabled' => $itemDisabled ?: ($rootDisabled ?: null),
        ];
    }

    /**
     * Get the collection from context.
     */
    protected function getCollection(): ?ListCollection
    {
        $collection = $this->get('collection');
        return $collection instanceof ListCollection ? $collection : null;
    }
}
