<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use Jramke\FluidPrimitives\Utility\DateUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Property\PropertyMapper;

final class DatePickerRenderingTest extends FunctionalTestCase
{
    private const string VIEW_DAY = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\DatePickerView::Day')}";
    private const string VIEW_MONTH = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\DatePickerView::Month')}";
    private const string VIEW_YEAR = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\DatePickerView::Year')}";

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function openStates(): iterable
    {
        yield 'closed by default' => ['', 'closed', false];
        yield 'open by default' => ['defaultOpen="{true}"', 'open', true];
        // The machine opens an inline date picker whatever `defaultOpen` says.
        yield 'inline' => ['inline="{true}"', 'open', true];
    }

    #[Test]
    #[DataProvider('openStates')]
    public function derivesTheOpenStateFromDefaultOpenAndInline(string $attributes, string $state, bool $open): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root ' . $attributes . '>
                <primitives:datePicker.control>
                    <primitives:datePicker.input />
                    <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
                </primitives:datePicker.control>
                <primitives:datePicker.positioner>
                    <primitives:datePicker.content>Calendar</primitives:datePicker.content>
                </primitives:datePicker.positioner>
            </primitives:datePicker.root>
        ');

        foreach (['root', 'input', 'trigger', 'content'] as $part) {
            $this->assertSame($state, $this->elementWith($html, 'data-date-picker-' . $part)->get('data-state'), $part);
        }
        $this->assertSame(
            $open ? 'true' : 'false',
            $this->elementWith($html, 'data-date-picker-trigger')->get('aria-expanded'),
        );
        $this->assertSame(!$open, $this->elementWith($html, 'data-date-picker-content')->has('hidden'));
    }

    #[Test]
    public function leavesTheFloatingStylesOffTheInlinePositioner(): void
    {
        $render = fn(string $attributes): string => $this->renderTemplate('
            <primitives:datePicker.root ' .
        $attributes .
        '>
                <primitives:datePicker.positioner>
                    <primitives:datePicker.content>Calendar</primitives:datePicker.content>
                </primitives:datePicker.positioner>
            </primitives:datePicker.root>
        ');

        // Without a trigger to anchor to nothing ever places an inline calendar, so floating
        // styles (absolute, top/left 0) would pin it to the corner of the page until hydration.
        $this->assertTrue($this->elementWith($render(''), 'data-date-picker-positioner')->has('style'));
        $this->assertFalse($this->elementWith($render('inline="{true}"'), 'data-date-picker-positioner')->has('style'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function emptyStates(): iterable
    {
        yield 'no date' => ['', true];
        yield 'a date' => ['defaultValue="2024-01-15"', false];
    }

    #[Test]
    #[DataProvider('emptyStates')]
    public function derivesTheEmptyStateFromDefaultValue(string $attributes, bool $empty): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root ' . $attributes . '>
                <primitives:datePicker.control>
                    <primitives:datePicker.input />
                    <primitives:datePicker.clearTrigger>Clear</primitives:datePicker.clearTrigger>
                </primitives:datePicker.control>
            </primitives:datePicker.root>
        ');

        $this->assertSame($empty, $this->elementWith($html, 'data-date-picker-root')->has('data-empty'));
        $this->assertSame($empty, $this->elementWith($html, 'data-date-picker-control')->has('data-placeholder-shown'));
        // there is nothing to clear
        $this->assertSame($empty, $this->elementWith($html, 'data-date-picker-clear-trigger')->has('hidden'));
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed, 2: string|list<string>|null}>
     */
    public static function datesForTheClient(): iterable
    {
        // 00:30 in Tokyo is still the day before in Berlin (the time zone of the server) and in UTC: the date that is
        // shipped is the one the object itself is on, whatever time zone that is.
        $tokyo = new \DateTime('2024-03-06 00:30:00', new \DateTimeZone('Asia/Tokyo'));
        $immutable = new \DateTimeImmutable('2024-03-07 08:00:00');

        yield 'defaultValue: a string' => ['defaultValue', '2024-01-15', ['2024-01-15']];
        yield 'defaultValue: a list' => ['defaultValue', ['2024-01-15', '2024-01-20'], ['2024-01-15', '2024-01-20']];
        // What a Field bound to an object property hands over.
        yield 'defaultValue: a DateTime' => ['defaultValue', $tokyo, ['2024-03-06']];
        yield 'defaultValue: a list of DateTimeInterface' => [
            'defaultValue',
            [$tokyo, $immutable],
            ['2024-03-06', '2024-03-07'],
        ];
        yield 'defaultValue: a date-time string' => ['defaultValue', '2024-01-15T10:30:00+01:00', ['2024-01-15']];
        yield 'defaultValue: not a date' => ['defaultValue', 'tomorrow', null];
        yield 'defaultValue: an empty list' => ['defaultValue', [], null];
        yield 'defaultValue: nothing' => ['defaultValue', null, null];

        // each of them has its own getter
        foreach (['min', 'max', 'defaultFocusedValue'] as $prop) {
            yield $prop . ': a DateTime' => [$prop, $tokyo, '2024-03-06'];
            yield $prop . ': not a date' => [$prop, 'tomorrow', null];
        }
    }

    /**
     * @param string|list<string>|null $expected
     */
    #[Test]
    #[DataProvider('datesForTheClient')]
    public function shipsTheDatesToTheClientAsIsoDates(string $prop, mixed $value, string|array|null $expected): void
    {
        $props = $this->renderHydrationProps($prop . '="{value}"', ['value' => $value]);

        if ($expected === null) {
            $this->assertArrayNotHasKey($prop, $props);

            return;
        }

        $this->assertSame($expected, $props[$prop]);
    }

    #[Test]
    public function submitsMidnightInTheServerTimeZoneThroughHiddenInputsNotTheVisibleInput(): void
    {
        $timeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            $expectations = [
                // An empty date picker renders no input here: the empty-value input submits its name.
                'no date' => ['', []],
                'one date' => ['defaultValue="2024-01-15"', ['2024-01-15T00:00:00+01:00']],
                // Summer and winter time, as the offset follows the date.
                'several dates' => [
                    'defaultValue="{0: \'2024-07-15\', 1: \'2024-01-20\'}"',
                    ['2024-07-15T00:00:00+02:00', '2024-01-20T00:00:00+01:00'],
                ],
            ];

            foreach ($expectations as $case => [$attributes, $expectedValues]) {
                $html = $this->renderTemplate('
                    <primitives:datePicker.root name="dates[]" ' .
                $attributes .
                '>
                        <primitives:datePicker.control>
                            <primitives:datePicker.input />
                        </primitives:datePicker.control>
                        <primitives:datePicker.hiddenInput />
                    </primitives:datePicker.root>
                ');

                // The visible input holds the date as typed, in the format of the locale.
                $this->assertFalse($this->elementWith($html, 'data-date-picker-input')->has('name'), $case);

                $hiddenInputs = $this->elementsWith($html, 'data-date-picker-hidden-input');
                $this->assertSame(
                    $expectedValues,
                    array_map(static fn($input): ?string => $input->get('value'), $hiddenInputs),
                    $case,
                );
                foreach ($hiddenInputs as $input) {
                    $this->assertSame('hidden', $input->get('type'), $case);
                    $this->assertSame('dates[]', $input->get('name'), $case);
                }
            }

            // The client builds the value of a date picked later in this time zone.
            $this->assertSame('Europe/Berlin', $this->renderHydrationProps('')['serverTimeZone']);
        } finally {
            date_default_timezone_set($timeZone);
        }
    }

    #[Test]
    public function mapsTheSubmittedValueToTheDateWithoutConfiguration(): void
    {
        $timeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            $mapper = $this->get(PropertyMapper::class);

            foreach (['2024-07-15', '2024-12-15'] as $isoDate) {
                $date = $mapper->convert(DateUtility::toW3cMidnight($isoDate), \DateTime::class);

                $this->assertInstanceOf(\DateTime::class, $date);
                $this->assertSame($isoDate . ' 00:00:00', $date->format('Y-m-d H:i:s'), $isoDate);
            }
        } finally {
            date_default_timezone_set($timeZone);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function initialViews(): iterable
    {
        yield 'defaults' => ['', 'day'];
        yield 'default view' => ['defaultView="' . self::VIEW_MONTH . '"', 'month'];
        yield 'below the min view' => ['minView="' . self::VIEW_MONTH . '"', 'month'];
        yield 'above a max view of day' => [
            'defaultView="' . self::VIEW_MONTH . '" maxView="' . self::VIEW_DAY . '"',
            'day',
        ];
        yield 'above the max view' => [
            'defaultView="' . self::VIEW_YEAR . '" maxView="' . self::VIEW_MONTH . '"',
            'month',
        ];
    }

    #[Test]
    #[DataProvider('initialViews')]
    public function startsInTheDefaultViewKeptBetweenMinViewAndMaxView(string $attributes, string $expectedView): void
    {
        $html = $this->renderTemplate('
            <primitives:datePicker.root ' . $attributes . '>
                <primitives:datePicker.view view="day">Day</primitives:datePicker.view>
                <primitives:datePicker.view view="month">Month</primitives:datePicker.view>
                <primitives:datePicker.view view="year">Year</primitives:datePicker.view>
            </primitives:datePicker.root>
        ');

        $views = $this->elementsByValue($html, 'data-date-picker-view');
        $this->assertSame(['day', 'month', 'year'], array_keys($views));
        $this->assertSame(
            array_map(static fn(string $view): bool => $view !== $expectedView, array_keys($views)),
            array_values(array_map(static fn($view): bool => $view->has('hidden'), $views)),
        );
    }

    #[Test]
    public function marksViewPartsWithTheirViewForTheClient(): void
    {
        // The client finds the part of a view by its `data-value`, and the months of a day view by
        // their `data-offset`.
        $html = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.view view="day">
                    <primitives:datePicker.viewControl view="day">
                        <primitives:datePicker.prevTrigger view="day" />
                        <primitives:datePicker.viewTrigger view="day" />
                        <primitives:datePicker.nextTrigger view="day" />
                    </primitives:datePicker.viewControl>
                    <primitives:datePicker.table view="day">
                        <primitives:datePicker.tableHeader />
                        <primitives:datePicker.tableBody />
                    </primitives:datePicker.table>
                    <primitives:datePicker.table view="day" offset="1" />
                </primitives:datePicker.view>
                <primitives:datePicker.presetTrigger value="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\DatePickerRangePreset::Last7Days\')}">Last 7 days</primitives:datePicker.presetTrigger>
            </primitives:datePicker.root>
        ');

        foreach (['view', 'view-control', 'prev-trigger', 'view-trigger', 'next-trigger'] as $part) {
            $this->assertSame('day', $this->elementWith($html, 'data-date-picker-' . $part)->get('data-value'), $part);
        }
        $tables = $this->elementsWith($html, 'data-date-picker-table');
        $this->assertSame(['0', '1'], array_map(static fn($table): ?string => $table->get('data-offset'), $tables));
        $this->assertSame('last7Days', $this->elementWith($html, 'data-date-picker-preset-trigger')->get('data-value'));
    }

    #[Test]
    public function rendersAndShipsTheLabelsInTheSiteLanguageUnlessOverridden(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:datePicker.root translations="{content: \'Kalender\'}">
                <primitives:datePicker.trigger />
                <primitives:datePicker.clearTrigger />
                <primitives:datePicker.positioner>
                    <primitives:datePicker.content>Calendar</primitives:datePicker.content>
                </primitives:datePicker.positioner>
                <primitives:datePicker.monthSelect />
                <primitives:datePicker.yearSelect />
            </primitives:datePicker.root>
        ');

        $this->assertSame(
            'Wert löschen',
            $this->elementWith($html, 'data-date-picker-clear-trigger')->get('aria-label'),
        );
        $this->assertSame(
            'Monat auswählen',
            $this->elementWith($html, 'data-date-picker-month-select')->get('aria-label'),
        );
        $this->assertSame(
            'Jahr auswählen',
            $this->elementWith($html, 'data-date-picker-year-select')->get('aria-label'),
        );
        $this->assertSame('Kalender öffnen', $this->elementWith($html, 'data-date-picker-trigger')->get('aria-label'));
        $content = $this->elementWith($html, 'data-date-picker-content');
        // The per-instance override wins over the language file.
        $this->assertSame('Kalender', $content->get('aria-label'));
        $this->assertSame('Datumsauswahl', $content->get('aria-roledescription'));

        // The client builds Zag's function labels from these texts and fills in the placeholders.
        $props = $this->firstHydrationProps();
        $this->assertSame('Wert löschen', $props['translations']['clearTrigger']);
        $this->assertSame('Kalender', $props['translations']['content']);
        $this->assertSame('Zum vorherigen Monat wechseln', $props['translations']['prevTriggerDay']);
        $this->assertSame('%date% auswählen', $props['translations']['dayCell']);
    }

    #[Test]
    public function shipsTheSiteLanguageAsLocaleUnlessOverridden(): void
    {
        $this->setRequestLocale('de_DE');

        $this->assertSame('de-DE', $this->renderHydrationProps('')['locale']);
        $this->assertSame('fr-CH', $this->renderHydrationProps('locale="fr-CH"')['locale']);
    }

    #[Test]
    public function takesStateAndIdsFromTheSurroundingField(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="birthDate" rootId="my-field" required="{true}" disabled="{true}">
                <primitives:datePicker.root>
                    <primitives:datePicker.label>Date of birth</primitives:datePicker.label>
                    <primitives:datePicker.control>
                        <primitives:datePicker.input />
                    </primitives:datePicker.control>
                    <primitives:datePicker.hiddenInput />
                </primitives:datePicker.root>
            </primitives:field.root>
        ');

        $input = $this->elementWith($html, 'data-date-picker-input');
        $this->assertTrue($input->has('required'));
        $this->assertTrue($input->has('disabled'));
        // nothing picked: the empty-value input is what submits the name
        $emptyValueInput = $this->elementWith($html, 'data-date-picker-empty-value-input');
        $this->assertSame('birthDate', $emptyValueInput->get('name'));
        $this->assertTrue($emptyValueInput->has('disabled'));

        // The Field's label and control ids have to land on the label and input part, which Zag
        // reads from `ids` - FieldIdMapping names them.
        $props = $this->firstHydrationProps();
        $this->assertSame('birthDate', $props['name']);
        $this->assertEquals(
            ['label' => 'field:my-field:label', 'input' => 'field:my-field:control'],
            array_intersect_key($props['ids'], ['label' => true, 'input' => true, 'control' => true]),
        );
    }

    /**
     * Renders a root with a trigger (so it hydrates) and returns the props it ships to the client.
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function renderHydrationProps(string $rootAttributes, array $variables = []): array
    {
        HydrationRegistry::getInstance()->clear();

        $this->renderTemplate('
            <primitives:datePicker.root ' . $rootAttributes . '>
                <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
            </primitives:datePicker.root>
        ', $variables);

        return $this->firstHydrationProps();
    }

    /**
     * @return array<string, mixed>
     */
    private function firstHydrationProps(): array
    {
        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];

        return array_values($hydrationData['date-picker'])[0]['props'];
    }
}
