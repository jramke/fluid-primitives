<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Domain\Dto\Positioning;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ComboboxRenderingTest extends FunctionalTestCase
{
    private const string POPUP_DIALOG = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\PopupType::Dialog')}";
    private const string SELECTION_CLEAR = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\ComboboxSelectionBehavior::Clear')}";

    private function cities(): ListCollection
    {
        return new ListCollection([
            ['value' => 'berlin', 'label' => 'Berlin'],
            ['value' => 'paris', 'label' => 'Paris'],
            ['value' => 'rome', 'label' => 'Rome', 'disabled' => true],
        ]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function openStates(): iterable
    {
        // `defaultOpen` used to have no explicit default, so `context.defaultOpen` was `null` rather than `false` when
        // unset. Fluid's inline ternary (`{x ? a : b}`) treats a bare `null` as truthy, unlike `f:if`, so the trigger
        // rendered a contradictory `aria-expanded="false" data-state="open"`.
        yield 'closed by default' => ['', 'closed', 'false', true];
        yield 'open' => ['defaultOpen="{true}"', 'open', 'true', false];
    }

    #[Test]
    public function namesThePartAndItsRootWhenAPartIsUsedOutsideTheRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('<combobox> must be used inside its root component (e.g. <ui:combobox.root>)');

        $this->renderTemplate(
            '<primitives:combobox.item item="{value: \'a\', label: \'A\'}">A</primitives:combobox.item>',
        );
    }

    #[Test]
    #[DataProvider('openStates')]
    public function rendersTheStateOnTheControlTheInputTheTriggerAndTheContent(
        string $arguments,
        string $state,
        string $expanded,
        bool $contentHidden,
    ): void {
        $html = $this->renderTemplate('
            <primitives:combobox.root rootId="my-combobox" ' .
        $arguments .
        '>
                <primitives:combobox.control>
                    <primitives:combobox.input />
                    <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
                </primitives:combobox.control>
                <primitives:combobox.positioner>
                    <primitives:combobox.content>
                        <primitives:combobox.list>List</primitives:combobox.list>
                    </primitives:combobox.content>
                </primitives:combobox.positioner>
            </primitives:combobox.root>
        ');

        foreach ([
            'data-combobox-control',
            'data-combobox-input',
            'data-combobox-trigger',
            'data-combobox-content',
        ] as $part) {
            $this->assertSame($state, $this->elementWith($html, $part)->get('data-state'), $part);
        }
        $this->assertSame($expanded, $this->elementWith($html, 'data-combobox-input')->get('aria-expanded'));
        $this->assertSame($expanded, $this->elementWith($html, 'data-combobox-trigger')->get('aria-expanded'));
        $this->assertSame($contentHidden, $this->elementWith($html, 'data-combobox-content')->has('hidden'));

        $this->assertSame(
            $state === 'open',
            HydrationRegistry::getInstance()->get('primitives', 'combobox', 'my-combobox')['props']['defaultOpen'],
        );
    }

    #[Test]
    public function shipsTheEnumPropsToTheClientAsTheirValues(): void
    {
        // Regression: `f:constant('Enum::Case')` (name passed positionally) silently fails to parse as a ViewHelper call
        // inside a `ui:prop` default - Fluid falls back to treating the whole `{...}` expression as literal text, so the
        // hydrated prop ended up as the raw string instead of the value of the enum. Only reproduces with the argument
        // passed positionally; `f:constant(name: 'Enum::Case')` (used by every other enum-typed prop default) is unaffected.
        $html = $this->renderTemplate('
            <primitives:combobox.root rootId="my-combobox">
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
            </primitives:combobox.root>
        ');

        $props = HydrationRegistry::getInstance()->get('primitives', 'combobox', 'my-combobox')['props'];
        $this->assertSame('none', $props['inputBehavior']);
        $this->assertSame('listbox', $props['popupType']);
        $this->assertSame('my-combobox', $this->elementWith($html, 'data-combobox-root')->get('data-combobox-root'));
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
        $html = $this->renderTemplate('
            <primitives:combobox.root ' . $arguments . '>
                <primitives:combobox.positioner>
                    <primitives:combobox.content>
                        <primitives:combobox.list>List</primitives:combobox.list>
                    </primitives:combobox.content>
                </primitives:combobox.positioner>
            </primitives:combobox.root>
        ');

        // markup mirrored from Zag, for the accessibility tree before the client hydrated
        $content = $this->elementWith($html, 'data-combobox-content');
        $this->assertSame('presentation', $content->get('role'));
        $this->assertFalse($content->has('aria-multiselectable'));

        $list = $this->elementWith($html, 'data-combobox-list');
        $this->assertSame('listbox', $list->get('role'));
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
        $html = $this->renderTemplate('
            <primitives:combobox.root rootId="my-combobox" ' .
        $arguments .
        '>
                <primitives:combobox.trigger>Open</primitives:combobox.trigger>
                <primitives:combobox.positioner>
                    <primitives:combobox.content><primitives:combobox.list>List</primitives:combobox.list></primitives:combobox.content>
                </primitives:combobox.positioner>
            </primitives:combobox.root>
        ');

        $this->assertSame($popupType, $this->elementWith($html, 'data-combobox-trigger')->get('aria-haspopup'));
        $this->assertSame($contentRole, $this->elementWith($html, 'data-combobox-content')->get('role'));
        $this->assertSame(
            $popupType,
            HydrationRegistry::getInstance()->get('primitives', 'combobox', 'my-combobox')['props']['popupType'],
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function triggerFocus(): iterable
    {
        yield 'a listbox popup keeps it out of the tab order' => ['', '', false];
        // the input moved into the content of a dialog, the trigger is what the user reaches it by
        yield 'a dialog popup makes it focusable' => ['popupType="' . self::POPUP_DIALOG . '"', '', true];
        yield 'its own prop over a listbox popup' => ['', 'focusable="{true}"', true];
        yield 'its own prop over a dialog popup' => [
            'popupType="' . self::POPUP_DIALOG . '"',
            'focusable="{false}"',
            false,
        ];
    }

    #[Test]
    #[DataProvider('triggerFocus')]
    public function resolvesTheTriggerFocusFromItsPropElseThePopupType(
        string $rootArguments,
        string $triggerArguments,
        bool $focusable,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:combobox.root ' .
            $rootArguments .
            '>
                <primitives:combobox.trigger ' .
            $triggerArguments .
            '>Open</primitives:combobox.trigger>
            </primitives:combobox.root>
        ',
        );

        $trigger = $this->elementWith($html, 'data-combobox-trigger');
        // the client reads `data-focusable`
        $this->assertSame($focusable, $trigger->has('data-focusable'));
        $this->assertSame($focusable ? null : '-1', $trigger->get('tabindex'));
    }

    #[Test]
    public function marksTheSelectedHighlightedAndDisabledItems(): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" defaultValue="berlin" defaultHighlightedValue="paris">
                <primitives:combobox.content>
                    <f:for each="{collection.items}" as="item">
                        <primitives:combobox.item item="{item}">
                            <primitives:combobox.itemText>{item.label}</primitives:combobox.itemText>
                        </primitives:combobox.item>
                    </f:for>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => $this->cities()]);

        $items = $this->elementsByValue($html, 'data-combobox-item');
        $this->assertSame(['berlin', 'paris', 'rome'], array_keys($items));
        $this->assertSame(
            [
                'berlin' => ['checked', 'true', false, null],
                'paris' => ['unchecked', null, true, null],
                'rome' => ['unchecked', null, false, 'true'],
            ],
            array_map(static fn($item): array => [
                $item->get('data-state'),
                $item->get('aria-selected'),
                $item->has('data-highlighted'),
                $item->get('aria-disabled'),
            ], $items),
        );

        // the text of an item carries the same state, for the styling of the text alone
        $texts = $this->elementsByValue($html, 'data-combobox-item-text');
        $this->assertSame(
            ['berlin' => 'checked', 'paris' => 'unchecked', 'rome' => 'unchecked'],
            array_map(static fn($text): ?string => $text->get('data-state'), $texts),
        );
    }

    #[Test]
    public function rendersTheItemOfAStencilWithoutAnItemAndMarksItsPartsForTheClient(): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root rootId="my-combobox" collection="{collection}">
                <primitives:combobox.content>
                    <ui:template name="itemTemplate" context="combobox">
                        <primitives:combobox.item>
                            <primitives:combobox.itemText>
                                <span {ui:ref(name: \'title\')}></span>
                            </primitives:combobox.itemText>
                            <primitives:combobox.itemIndicator />
                        </primitives:combobox.item>
                    </ui:template>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);

        $template = $this->elementWith($html, 'data-combobox-item-template');
        $this->assertSame('template', $template->tag);
        $this->assertSame('my-combobox', $template->get('data-combobox-item-template'));
        foreach ([
            'data-combobox-item',
            'data-combobox-item-text',
            'data-combobox-item-indicator',
            'data-combobox-title',
        ] as $part) {
            $this->assertSame('my-combobox', $this->elementWith($html, $part)->get($part), $part);
        }

        // nothing is selected in a stencil: the client sets the state on each copy it makes
        $item = $this->elementWith($html, 'data-combobox-item');
        $this->assertSame('unchecked', $item->get('data-state'));
        $this->assertFalse($item->has('aria-selected'));
    }

    #[Test]
    public function throwsWhenItemIsMissingOutsideTemplate(): void
    {
        $this->expectExceptionMessage("requires an 'item' prop, unless used inside a ui:template block");

        $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.item>
                        <span>content</span>
                    </primitives:combobox.item>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function emptyCollections(): iterable
    {
        // the only rendering of `ui:listCollection`
        yield 'a collection of the ViewHelper' => [
            '<ui:listCollection as="collection" />',
            'collection="{collection}"',
        ];
        // pure async usage: the options only ever come from `searchUrl`
        yield 'no collection at all' => ['', 'searchUrl="/search"'];
    }

    #[Test]
    #[DataProvider('emptyCollections')]
    public function rendersWithoutOptionsForPureAsyncUsage(string $before, string $arguments): void
    {
        // The content reads `context.collection.size` through Fluid's own property-path resolution while the
        // collection is genuinely null - that used to crash on a protected method.
        $html = $this->renderTemplate($before . '
            <primitives:combobox.root ' . $arguments . '>
                <primitives:combobox.content></primitives:combobox.content>
            </primitives:combobox.root>
        ');

        $this->assertTrue($this->elementWith($html, 'data-combobox-content')->has('data-empty'));
    }

    /**
     * @return iterable<string, array{0: list<array{value: string, label: string}>, 1: bool}>
     */
    public static function collectionSizes(): iterable
    {
        yield 'no items' => [[], false];
        yield 'items' => [[['value' => 'berlin', 'label' => 'Berlin']], true];
    }

    /**
     * @param list<array{value: string, label: string}> $items
     */
    #[Test]
    #[DataProvider('collectionSizes')]
    public function showsTheEmptyPartOnlyWhileThereAreNoItems(array $items, bool $hidden): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>No results found</primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection($items)]);

        $this->assertSame($hidden, $this->elementWith($html, 'data-combobox-empty')->has('hidden'));
        $this->assertSame(!$hidden, $this->elementWith($html, 'data-combobox-content')->has('data-empty'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string|list<string>|null, 3: list<string>}>
     */
    public static function hiddenInputs(): iterable
    {
        // nothing selected still submits the name, like a native input does, and gives a field's focus something to land on
        yield 'nothing selected' => ['country', '', null, ['']];
        yield 'an empty list' => ['country', '', [], ['']];
        yield 'one value' => ['country', '', 'us', ['us']];
        yield 'several values' => ['countries[]', 'multiple="{true}"', ['us', 'fr'], ['us', 'fr']];
    }

    /**
     * @param string|list<string>|null $defaultValue
     * @param list<string> $values
     */
    #[Test]
    #[DataProvider('hiddenInputs')]
    public function submitsTheValuesNotTheLabelsEachInAHiddenInput(
        string $name,
        string $arguments,
        string|array|null $defaultValue,
        array $values,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:combobox.root collection="{collection}" name="' .
            $name .
            '" defaultValue="{defaultValue}" ' .
            $arguments .
            '>
                <primitives:combobox.hiddenInput />
            </primitives:combobox.root>
        ',
            [
                'collection' => new ListCollection([
                    ['value' => 'us', 'label' => 'United States'],
                    ['value' => 'de', 'label' => 'Germany'],
                    ['value' => 'fr', 'label' => 'France'],
                ]),
                'defaultValue' => $defaultValue,
            ],
        );

        $inputs = $this->elementsWith($html, 'data-combobox-hidden-input');
        $this->assertSame($values, array_map(static fn($input): ?string => $input->get('value'), $inputs));
        foreach ($inputs as $input) {
            $this->assertSame('text', $input->get('type'));
            $this->assertSame($name, $input->get('name'));
            $this->assertSame('true', $input->get('aria-hidden'));
            $this->assertSame('-1', $input->get('tabindex'));
        }
        $this->assertStringNotContainsString('United States', $html);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function initialInputValues(): iterable
    {
        yield 'the label of the selected item' => ['defaultValue="paris"', 'Paris'];
        yield 'nothing selected' => ['', ''];
        yield 'several values selected' => ['multiple="{true}" defaultValue="{0: \'paris\'}"', ''];
        yield 'an input value of its own' => ['defaultValue="paris" defaultInputValue="Par"', 'Par'];
        yield 'a selection behavior that clears the input' => [
            'defaultValue="paris" selectionBehavior="' . self::SELECTION_CLEAR . '"',
            '',
        ];
    }

    #[Test]
    #[DataProvider('initialInputValues')]
    public function fillsTheInputWithTheLabelOfTheSelectedItemUnlessTheBehaviorSaysOtherwise(
        string $arguments,
        string $value,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:combobox.root collection="{collection}" ' . $arguments . '>
                <primitives:combobox.control>
                    <primitives:combobox.input />
                </primitives:combobox.control>
            </primitives:combobox.root>
        ',
            ['collection' => $this->cities()],
        );

        $this->assertSame($value, $this->elementWith($html, 'data-combobox-input')->get('value') ?? '');
    }

    #[Test]
    public function rendersTheContentInsideAPortalWithTheMarkerOfTheRoot(): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}" rootId="portaled-combobox">
                <primitives:combobox.trigger>Toggle</primitives:combobox.trigger>
                <ui:portal>
                    <primitives:combobox.content>
                        <f:for each="{collection.items}" as="item">
                            <primitives:combobox.item item="{item}">
                                <primitives:combobox.itemText>{item.label}</primitives:combobox.itemText>
                            </primitives:combobox.item>
                        </f:for>
                    </primitives:combobox.content>
                </ui:portal>
            </primitives:combobox.root>
        ', ['collection' => $this->cities()]);

        // the root and the trigger stay where they are, the content is gone from the markup ...
        $this->assertSame(
            'portaled-combobox',
            $this->elementWith($html, 'data-combobox-trigger')->get('data-combobox-trigger'),
        );
        $this->assertSame([], $this->elementsWith($html, 'data-combobox-content'));

        // ... and is rendered elsewhere, still marked with the id of the root it belongs to
        $portaled = implode('', PortalRegistry::getInstance()->getAllByName('default'));
        $this->assertSame(
            'portaled-combobox',
            $this->elementWith($portaled, 'data-combobox-content')->get('data-combobox-content'),
        );
        $this->assertSame(
            ['berlin', 'paris', 'rome'],
            array_keys($this->elementsByValue($portaled, 'data-combobox-item')),
        );
    }

    #[Test]
    public function shipsTheDefaultPositioningAsThePositioningObjectItMeans(): void
    {
        $html = $this->renderTemplate('<primitives:combobox.root collection="{cities}" />', [
            'cities' => $this->cities(),
        ]);

        $rootId = (string)$this->elementWith($html, 'data-combobox-root')->get('data-combobox-root');
        $positioning = HydrationRegistry::getInstance()->get('primitives', 'combobox', $rootId)['props']['positioning'];

        $this->assertInstanceOf(Positioning::class, $positioning);
        // `sameWidth: '{true}'` in the default of the prop is the boolean the client reads
        $this->assertSame(['placement' => 'bottom', 'sameWidth' => true], $positioning->jsonSerialize());
    }
}
