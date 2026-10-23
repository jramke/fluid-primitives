<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SelectRenderingTest extends FunctionalTestCase
{
    private const string POPUP_DIALOG = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\PopupType::Dialog')}";

    private function collection(): ListCollection
    {
        return new ListCollection([
            ['value' => 'opt-1', 'label' => 'Option 1'],
            ['value' => 'opt-2', 'label' => 'Option 2', 'disabled' => true],
            ['value' => 'opt-3', 'label' => 'Option 3'],
        ]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function openStates(): iterable
    {
        // `defaultOpen` used to have no explicit default, so `context.defaultOpen` was `null` rather than `false` when
        // unset. Fluid's inline ternary (`{x ? a : b}`) treats a bare `null` as truthy, unlike `f:if`, so the parts
        // rendered `data-state="open"` by default.
        yield 'closed by default' => ['', 'closed', false];
        yield 'open' => ['defaultOpen="{true}"', 'open', true];
    }

    #[Test]
    #[DataProvider('openStates')]
    public function rendersTheStateOnTheControlTheTriggerAndTheContent(
        string $arguments,
        string $state,
        bool $open,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:select.root rootId="my-select" collection="{collection}" ' . $arguments . '>
                <primitives:select.control>
                    <primitives:select.trigger>Select an option</primitives:select.trigger>
                </primitives:select.control>
                <primitives:select.positioner>
                    <primitives:select.content>
                        <primitives:select.list>List</primitives:select.list>
                    </primitives:select.content>
                </primitives:select.positioner>
            </primitives:select.root>
        ',
            ['collection' => $this->collection()],
        );

        // what the client queries every part by
        $this->assertSame('my-select', $this->elementWith($html, 'data-select-root')->get('data-select-root'));
        foreach (['data-select-control', 'data-select-trigger', 'data-select-content'] as $part) {
            $this->assertSame($state, $this->elementWith($html, $part)->get('data-state'), $part);
        }

        $trigger = $this->elementWith($html, 'data-select-trigger');
        $this->assertSame('button', $trigger->tag);
        // a button inside a form submits it when clicked, before the client has hydrated
        $this->assertSame('button', $trigger->get('type'));
        $this->assertSame('combobox', $trigger->get('role'));
        $this->assertSame('false', $trigger->get('aria-invalid'));
        $this->assertSame($open, $trigger->has('data-expanded'));
        // an open select that rendered its content hidden would open only after hydration
        $this->assertSame(!$open, $this->elementWith($html, 'data-select-content')->has('hidden'));

        $this->assertSame(
            $open,
            HydrationRegistry::getInstance()->get('primitives', 'select', 'my-select')['props']['defaultOpen'],
        );
    }

    #[Test]
    public function marksTheTriggerDisabledInvalidAndReadOnly(): void
    {
        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}" disabled="{true}" invalid="{true}" readOnly="{true}">
                <primitives:select.control>
                    <primitives:select.trigger>Select an option</primitives:select.trigger>
                </primitives:select.control>
            </primitives:select.root>
        ', ['collection' => $this->collection()]);

        $trigger = $this->elementWith($html, 'data-select-trigger');
        foreach (['data-disabled', 'data-invalid', 'data-readonly', 'disabled'] as $flag) {
            $this->assertTrue($trigger->has($flag), $flag);
        }
        $this->assertSame('true', $trigger->get('aria-invalid'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function selections(): iterable
    {
        yield 'nothing selected' => ['', true];
        yield 'an empty string, which is what an empty string property of an object binds' => ['defaultValue=""', true];
        yield 'a value that is not in the collection' => ['defaultValue="unknown"', true];
        yield 'a value selected' => ['defaultValue="opt-3"', false];
    }

    #[Test]
    #[DataProvider('selections')]
    public function showsThePlaceholderAndHidesTheClearTriggerOnlyWhileNothingIsSelected(
        string $arguments,
        bool $placeholderShown,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:select.root collection="{collection}" ' . $arguments . '>
                <primitives:select.control>
                    <primitives:select.trigger>
                        <primitives:select.valueText placeholder="Pick one" />
                    </primitives:select.trigger>
                    <primitives:select.clearTrigger>x</primitives:select.clearTrigger>
                </primitives:select.control>
            </primitives:select.root>
        ',
            ['collection' => $this->collection()],
        );

        $this->assertSame(
            $placeholderShown,
            $this->elementWith($html, 'data-select-trigger')->has('data-placeholder-shown'),
        );
        $this->assertSame($placeholderShown, $this->elementWith($html, 'data-select-clear-trigger')->has('hidden'));
        preg_match('/data-select-value-text[^>]*>\s*([^<]*?)\s*</', $html, $valueText);
        $this->assertSame($placeholderShown ? 'Pick one' : 'Option 3', $valueText[1] ?? null);
    }

    #[Test]
    public function marksTheSelectedAndTheDisabledItemsAndSelectsTheNativeOption(): void
    {
        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}" defaultValue="opt-3">
                <primitives:select.positioner>
                    <primitives:select.content>
                        <primitives:select.list>
                            <f:for each="{collection.items}" as="item">
                                <primitives:select.item item="{item}">{item.label}</primitives:select.item>
                            </f:for>
                        </primitives:select.list>
                    </primitives:select.content>
                </primitives:select.positioner>
                <primitives:select.hiddenSelect />
            </primitives:select.root>
        ', ['collection' => $this->collection()]);

        $items = $this->elementsByValue($html, 'data-select-item');
        $this->assertSame(['opt-1', 'opt-2', 'opt-3'], array_keys($items));
        $this->assertSame(
            ['opt-1' => [null, false, false], 'opt-2' => [null, false, true], 'opt-3' => ['true', true, false]],
            array_map(static fn($item): array => [
                $item->get('aria-selected'),
                $item->has('data-selected'),
                $item->has('data-disabled'),
            ], $items),
        );
        $this->assertSame('true', $items['opt-2']->get('aria-disabled'));

        // what the form submits before the client has hydrated
        preg_match_all('/<option value="([^"]*)"([^>]*)>/', $html, $options, PREG_SET_ORDER);
        $selected = [];
        foreach ($options as [, $value, $attributes]) {
            $selected[$value] = str_contains($attributes, 'selected');
        }
        $this->assertSame(['' => false, 'opt-1' => false, 'opt-2' => false, 'opt-3' => true], $selected);
    }

    #[Test]
    public function rendersTheItemOfAStencilWithoutAnItemAndMarksItsPartsForTheClient(): void
    {
        $html = $this->renderTemplate('
            <primitives:select.root rootId="my-select" collection="{collection}">
                <primitives:select.positioner>
                    <primitives:select.content>
                        <primitives:select.list>
                            <ui:template name="itemTemplate" context="select">
                                <primitives:select.item>
                                    <primitives:select.itemText />
                                    <primitives:select.itemIndicator />
                                </primitives:select.item>
                            </ui:template>
                        </primitives:select.list>
                    </primitives:select.content>
                </primitives:select.positioner>
            </primitives:select.root>
        ', ['collection' => new ListCollection([])]);

        $template = $this->elementWith($html, 'data-select-item-template');
        $this->assertSame('template', $template->tag);
        $this->assertSame('my-select', $template->get('data-select-item-template'));
        foreach (['data-select-item', 'data-select-item-text', 'data-select-item-indicator'] as $part) {
            $this->assertSame('my-select', $this->elementWith($html, $part)->get($part), $part);
        }

        // nothing is selected in a stencil: the client sets the state on each copy it makes
        $this->assertFalse($this->elementWith($html, 'data-select-item')->has('aria-selected'));
    }

    #[Test]
    public function rendersTheNativeSelectWithTheFormAttributesOfTheRoot(): void
    {
        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}" name="country" form="signup" multiple="{true}" required="{true}">
                <primitives:select.hiddenSelect />
            </primitives:select.root>
        ', ['collection' => $this->collection()]);

        $select = $this->elementWith($html, 'data-select-hidden-select');
        $this->assertSame('select', $select->tag);
        $this->assertSame('country', $select->get('name'));
        $this->assertSame('signup', $select->get('form'));
        $this->assertTrue($select->has('multiple'));
        $this->assertTrue($select->has('required'));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string}>
     */
    public static function multiples(): iterable
    {
        yield 'a single value' => ['', null];
        yield 'several values' => ['multiple="{true}"', 'true'];
    }

    #[Test]
    #[DataProvider('multiples')]
    public function rendersTheListboxOnTheListPartInsideAPresentationalContent(
        string $arguments,
        ?string $multiselectable,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:select.root collection="{collection}" ' . $arguments . '>
                <primitives:select.positioner>
                    <primitives:select.content>
                        <primitives:select.list>List</primitives:select.list>
                    </primitives:select.content>
                </primitives:select.positioner>
            </primitives:select.root>
        ',
            ['collection' => $this->collection()],
        );

        // markup mirrored from Zag, for the accessibility tree before the client hydrated
        $content = $this->elementWith($html, 'data-select-content');
        $this->assertSame('presentation', $content->get('role'));
        $this->assertFalse($content->has('tabindex'));

        $list = $this->elementWith($html, 'data-select-list');
        $this->assertSame('listbox', $list->get('role'));
        $this->assertSame('0', $list->get('tabindex'));
        $this->assertSame($multiselectable, $list->get('aria-multiselectable'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function popupTypes(): iterable
    {
        yield 'a listbox by default' => ['', 'listbox', 'presentation'];
        yield 'a dialog' => ['popupType="' . self::POPUP_DIALOG . '"', 'dialog', 'dialog'];
    }

    #[Test]
    #[DataProvider('popupTypes')]
    public function rendersTheTriggerAndTheContentForThePopupTypeAndShipsItToTheClient(
        string $arguments,
        string $popupType,
        string $contentRole,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:select.root rootId="my-select" collection="{collection}" ' . $arguments . '>
                <primitives:select.control>
                    <primitives:select.trigger>Open</primitives:select.trigger>
                </primitives:select.control>
                <primitives:select.positioner>
                    <primitives:select.content><primitives:select.list>List</primitives:select.list></primitives:select.content>
                </primitives:select.positioner>
            </primitives:select.root>
        ',
            ['collection' => $this->collection()],
        );

        $this->assertSame($popupType, $this->elementWith($html, 'data-select-trigger')->get('aria-haspopup'));
        $this->assertSame($contentRole, $this->elementWith($html, 'data-select-content')->get('role'));
        $this->assertSame(
            $popupType,
            HydrationRegistry::getInstance()->get('primitives', 'select', 'my-select')['props']['popupType'],
        );
    }

    /**
     * @return iterable<string, array{0: string|list<string>|null, 1: ?list<string>}>
     */
    public static function defaultValues(): iterable
    {
        yield 'a string' => ['opt-1', ['opt-1']];
        yield 'a list' => [['opt-1', 'opt-3'], ['opt-1', 'opt-3']];
        yield 'nothing' => [null, null];
        yield 'an empty string' => ['', null];
    }

    /**
     * @param string|list<string>|null $defaultValue
     * @param ?list<string> $expected
     */
    #[Test]
    #[DataProvider('defaultValues')]
    public function shipsTheDefaultValueToTheClientAsAListUnlessThereIsNone(
        string|array|null $defaultValue,
        ?array $expected,
    ): void {
        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}" defaultValue="{defaultValue}">
                <primitives:select.hiddenSelect />
            </primitives:select.root>
        ', [
            'collection' => $this->collection(),
            'defaultValue' => $defaultValue,
        ]);

        $rootId = (string)$this->elementWith($html, 'data-select-root')->get('data-select-root');
        $props = HydrationRegistry::getInstance()->get('primitives', 'select', $rootId)['props'];

        if ($expected === null) {
            // excluded rather than shipped as null, which the machine would take for a value
            $this->assertArrayNotHasKey('defaultValue', $props);

            return;
        }

        $this->assertSame($expected, $props['defaultValue']);
    }

    #[Test]
    public function shipsTheCollectionToTheClientWithTheKeysItReadsItBy(): void
    {
        $html = $this->renderTemplate('
            <primitives:select.root collection="{collection}">
                <primitives:select.hiddenSelect />
            </primitives:select.root>
        ', ['collection' => $this->collection()]);

        $rootId = (string)$this->elementWith($html, 'data-select-root')->get('data-select-root');
        $collection = json_decode(
            json_encode(
                HydrationRegistry::getInstance()->get('primitives', 'select', $rootId)['props']['collection'],
                JSON_THROW_ON_ERROR,
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            [
                ['value' => 'opt-1', 'label' => 'Option 1'],
                ['value' => 'opt-2', 'label' => 'Option 2', 'disabled' => true],
                ['value' => 'opt-3', 'label' => 'Option 3'],
            ],
            $collection['items'],
        );
        // what getListCollectionFromHydrationData() rebuilds the collection from: the client computes the rest
        $this->assertSame(
            ['items', 'itemToValueKey', 'itemToStringKey', 'isItemDisabledKey', 'groupByKey', 'groupSort'],
            array_keys($collection),
        );
    }
}
