<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Domain\Dto\ListCollectionItem;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasListCollectionTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
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
    /**
     * @return array{clearTriggerLabel: string|false}
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'clearTriggerLabel' => 'select.clearTriggerLabel',
        ]);
    }

    /**
     * Get the state of a select item (selected, disabled) - or, with no item at all, the same
     * placeholder shape (nothing selected/disabled) a stencil-mode caller would otherwise have to
     * hardcode itself. Select has no `ui:template` usage of its own today, but this mirrors
     * ComboboxContext::getItemState() for consistency and in case that changes.
     *
     * @param ListCollectionItem|array|null $item The item to get state for
     * @return object Object with 'selected' and 'disabled' properties
     */
    public function getItemState(ListCollectionItem|array|null $item): object
    {
        if ($item === null) {
            return (object)['selected' => false, 'disabled' => false];
        }

        $defaultValue = $this->getDefaultValue() ?? [];
        $rootDisabled = Typed::bool($this->get('disabled'));

        // Handle ListCollectionItem objects directly
        if ($item instanceof ListCollectionItem) {
            return (object)[
                'selected' => in_array($item->value, $defaultValue, strict: true),
                'disabled' => $item->disabled ?: ($rootDisabled ?: null),
            ];
        }

        // Fallback for raw items
        $collection = $this->getCollection();
        $value = $collection?->getItemValue($item);
        $itemDisabled = $collection?->getItemDisabled($item) ?? false;

        return (object)[
            'selected' => in_array($value, $defaultValue, strict: true),
            'disabled' => $itemDisabled ?: ($rootDisabled ?: null),
        ];
    }
}
