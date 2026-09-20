<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class FieldArrayContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'rowAdded' => 'fieldArray.rowAdded',
            'rowRemoved' => 'fieldArray.rowRemoved',
        ]);
    }

    /**
     * Whether `itemGroup` has any rows yet, from the server-rendered `itemCount` - lets
     * `emptyState` start hidden/shown correctly before JavaScript hydrates (client-side
     * append/remove take over from there), the same way `Combobox.Empty` derives its own
     * visibility from `context.collection.size` instead of only toggling client-side.
     */
    public function getHasItems(): bool
    {
        return Typed::int($this->get('itemCount')) > 0;
    }

    /**
     * Whether `append()` (and a click on `addTrigger`) would currently add a row - mirrors
     * `field-array.connect.ts`'s own client-side `canAppend()`, computed here from the
     * server-rendered `itemCount` instead of a live DOM count.
     */
    public function getCanAppend(): bool
    {
        $maxItems = Typed::intOrNull($this->get('maxItems'));
        return $maxItems === null || Typed::int($this->get('itemCount')) < $maxItems;
    }

    /**
     * Whether `remove()` (and a click on any row's `removeTrigger`) would currently remove a
     * row - mirrors `field-array.connect.ts`'s own client-side `canRemove()`.
     *
     * Always `true` inside `itemTemplate`'s stencil (no real `item.index` yet - see
     * `FieldContext::beforeRendering()` for the same null check). That `removeTrigger` is never
     * shown directly, only cloned client-side, so baking `aria-disabled` into it from whatever
     * `itemCount`/`minItems` happen to be at page-load time would stick on every clone: a
     * freshly-cloned node's first `spreadProps()` call in `field-array.connect.ts` can't tell
     * "already `true` in the static HTML" apart from "never set" (both read as the same
     * `undefined` in its old-vs-new diff) and skips reconciling it, leaving the clone stuck
     * disabled forever. Safe to hardcode `true` here regardless of `minItems`, because a row that
     * was *just* added can never legitimately need its own remove button pre-disabled - appending
     * only ever makes removal more permissive, never less.
     */
    public function getCanRemove(): bool
    {
        if (Typed::intOrNull($this->get('item.index')) === null) {
            return true;
        }

        $minItems = Typed::intOrNull($this->get('minItems')) ?? 0;
        return Typed::int($this->get('itemCount')) > $minItems;
    }
}
