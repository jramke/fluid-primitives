<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Domain\Dto\ListCollectionItem;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasListCollectionTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class SelectContext extends AbstractComponentContext
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

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        $defaultValue = $this->get('defaultValue');
        if ($defaultValue === null || $defaultValue === []) {
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
        return $this->translationsWithDefaults([
            'clearTriggerLabel' => 'select.clearTriggerLabel',
        ]);
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
}
