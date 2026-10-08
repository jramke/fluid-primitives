<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class DatePickerRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function derivesTheOpenStateFromDefaultOpenAndInline(): void
    {
        $render = fn(string $attributes): string => $this->renderTemplate('
            <primitives:datePicker.root ' .
        $attributes .
        '>
                <primitives:datePicker.control>
                    <primitives:datePicker.input />
                    <primitives:datePicker.trigger>Open</primitives:datePicker.trigger>
                </primitives:datePicker.control>
                <primitives:datePicker.positioner>
                    <primitives:datePicker.content>Calendar</primitives:datePicker.content>
                </primitives:datePicker.positioner>
            </primitives:datePicker.root>
        ');

        $closed = $render('');
        $this->assertStringContainsString('data-date-picker-root="', $closed);
        $this->assertStringContainsString('data-state="closed"', $closed);
        $this->assertStringNotContainsString('data-state="open"', $closed);
        $this->assertStringContainsString('aria-expanded="false"', $closed);
        $this->assertMatchesRegularExpression('/data-date-picker-content="[^"]*"[^>]*hidden/', $closed);

        // The machine opens an inline date picker whatever `defaultOpen` says.
        foreach (['defaultOpen="{true}"', 'inline="{true}"'] as $attributes) {
            $open = $render($attributes);
            $this->assertStringContainsString('data-state="open"', $open, $attributes);
            $this->assertStringNotContainsString('data-state="closed"', $open, $attributes);
            $this->assertStringContainsString('aria-expanded="true"', $open, $attributes);
            $this->assertDoesNotMatchRegularExpression(
                '/data-date-picker-content="[^"]*"[^>]*hidden/',
                $open,
                $attributes,
            );
        }
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
        $this->assertStringContainsString('style=', $this->extractTag($render(''), 'data-date-picker-positioner'));
        $this->assertStringNotContainsString('style=', $this->extractTag(
            $render('inline="{true}"'),
            'data-date-picker-positioner',
        ));
    }

    #[Test]
    public function derivesTheEmptyStateFromDefaultValue(): void
    {
        $render = fn(string $attributes): string => $this->renderTemplate('
            <primitives:datePicker.root ' .
        $attributes .
        '>
                <primitives:datePicker.control>
                    <primitives:datePicker.input />
                    <primitives:datePicker.clearTrigger>Clear</primitives:datePicker.clearTrigger>
                </primitives:datePicker.control>
            </primitives:datePicker.root>
        ');

        $empty = $render('');
        $this->assertStringContainsString('data-empty', $this->extractTag($empty, 'data-date-picker-root'));
        $this->assertStringContainsString('data-placeholder-shown', $this->extractTag(
            $empty,
            'data-date-picker-control',
        ));
        $this->assertStringContainsString('hidden', $this->extractTag($empty, 'data-date-picker-clear-trigger'));

        $filled = $render('defaultValue="2024-01-15"');
        $this->assertStringNotContainsString('data-empty', $this->extractTag($filled, 'data-date-picker-root'));
        $this->assertStringNotContainsString('data-placeholder-shown', $this->extractTag(
            $filled,
            'data-date-picker-control',
        ));
        $this->assertStringNotContainsString('hidden', $this->extractTag($filled, 'data-date-picker-clear-trigger'));
    }

    #[Test]
    public function shipsDefaultValueToTheClientAsAListOfIsoDates(): void
    {
        $dateTime = new \DateTime('2024-03-05 23:30:00', new \DateTimeZone('Europe/Berlin'));
        $immutable = new \DateTimeImmutable('2024-03-07 08:00:00');

        $expectations = [
            'string' => ['2024-01-15', ['2024-01-15']],
            'list' => [
                ['2024-01-15', '2024-01-20'],
                ['2024-01-15', '2024-01-20'],
            ],
            // What a Field bound to an object property hands over.
            'DateTime' => [$dateTime, ['2024-03-05']],
            'list of DateTimeInterface' => [
                [$dateTime,    $immutable],
                ['2024-03-05', '2024-03-07'],
            ],
            'date-time string' => ['2024-01-15T10:30:00+01:00', ['2024-01-15']],
            'not a date' => ['tomorrow', null],
            'empty list' => [[], null],
            'null' => [null, null],
        ];

        foreach ($expectations as $case => [$value, $expected]) {
            $props = $this->renderHydrationProps('defaultValue="{value}"', ['value' => $value]);

            if ($expected === null) {
                $this->assertArrayNotHasKey('defaultValue', $props, $case);
            } else {
                $this->assertSame($expected, $props['defaultValue'], $case);
            }
        }
    }

    #[Test]
    public function startsInTheDefaultViewKeptBetweenMinViewAndMaxView(): void
    {
        $view = static fn(string $case): string => (
            '{f:constant(name: \'Jramke\FluidPrimitives\Enum\DatePickerView::' . $case . '\')}'
        );

        $expectations = [
            'defaults' => ['', 'day'],
            'default view' => ['defaultView="' . $view('Month') . '"', 'month'],
            'below the min view' => ['minView="' . $view('Month') . '"', 'month'],
            'above the max view' => ['defaultView="' . $view('Year') . '" maxView="' . $view('Month') . '"', 'month'],
        ];

        foreach ($expectations as $case => [$attributes, $expectedView]) {
            $html = $this->renderTemplate('
                <primitives:datePicker.root ' . $attributes . '>
                    <primitives:datePicker.view view="day">Day</primitives:datePicker.view>
                    <primitives:datePicker.view view="month">Month</primitives:datePicker.view>
                    <primitives:datePicker.view view="year">Year</primitives:datePicker.view>
                </primitives:datePicker.root>
            ');

            foreach (['day', 'month', 'year'] as $candidate) {
                $viewTag = $this->extractTag($html, 'data-date-picker-view', 'data-value="' . $candidate . '"');
                if ($candidate === $expectedView) {
                    $this->assertStringNotContainsString('hidden', $viewTag, $case);
                } else {
                    $this->assertStringContainsString('hidden', $viewTag, $case . ' / ' . $candidate);
                }
            }
        }
    }

    #[Test]
    public function marksViewPartsWithTheirViewForTheClient(): void
    {
        // The client finds the part of a view by its `data-value`.
        $html = $this->renderTemplate('
            <primitives:datePicker.root>
                <primitives:datePicker.view view="month">
                    <primitives:datePicker.viewControl view="month">
                        <primitives:datePicker.prevTrigger view="month" />
                        <primitives:datePicker.viewTrigger view="month" />
                        <primitives:datePicker.nextTrigger view="month" />
                    </primitives:datePicker.viewControl>
                    <primitives:datePicker.table view="month">
                        <primitives:datePicker.tableHeader view="month" />
                        <primitives:datePicker.tableBody view="month" />
                    </primitives:datePicker.table>
                </primitives:datePicker.view>
                <primitives:datePicker.presetTrigger value="last7Days">Last 7 days</primitives:datePicker.presetTrigger>
            </primitives:datePicker.root>
        ');

        foreach ([
            'view',
            'view-control',
            'prev-trigger',
            'view-trigger',
            'next-trigger',
            'table',
            'table-header',
            'table-body',
        ] as $part) {
            $this->assertStringContainsString(
                'data-value="month"',
                $this->extractTag($html, 'data-date-picker-' . $part),
                $part,
            );
        }
        $this->assertStringContainsString('data-value="last7Days"', $this->extractTag(
            $html,
            'data-date-picker-preset-trigger',
        ));
    }

    #[Test]
    public function rendersAndShipsTheLabelsInTheSiteLanguageUnlessOverridden(): void
    {
        $this->setRequestLocale('de_DE');

        $template = '
            <primitives:datePicker.root translations="{content: \'Kalender\'}">
                <primitives:datePicker.clearTrigger />
                <primitives:datePicker.positioner>
                    <primitives:datePicker.content>Calendar</primitives:datePicker.content>
                </primitives:datePicker.positioner>
                <primitives:datePicker.monthSelect />
                <primitives:datePicker.yearSelect />
            </primitives:datePicker.root>
        ';
        $html = $this->renderTemplate($template);

        $this->assertStringContainsString('aria-label="Wert löschen"', $this->extractTag(
            $html,
            'data-date-picker-clear-trigger',
        ));
        $this->assertStringContainsString('aria-label="Monat auswählen"', $this->extractTag(
            $html,
            'data-date-picker-month-select',
        ));
        $this->assertStringContainsString('aria-label="Jahr auswählen"', $this->extractTag(
            $html,
            'data-date-picker-year-select',
        ));
        // The per-instance override wins over the language file.
        $this->assertStringContainsString('aria-label="Kalender"', $this->extractTag(
            $html,
            'data-date-picker-content',
        ));

        $props = $this->firstHydrationProps();
        $this->assertSame('Wert löschen', $props['translations']['clearTrigger']);
        $this->assertSame('Kalender', $props['translations']['content']);
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
                </primitives:datePicker.root>
            </primitives:field.root>
        ');

        $inputTag = $this->extractTag($html, 'data-date-picker-input');
        $this->assertStringContainsString('name="birthDate"', $inputTag);
        $this->assertStringContainsString('required', $inputTag);
        $this->assertStringContainsString('disabled', $inputTag);

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

    /**
     * The opening tag of the element carrying `$attribute`, and `$alsoContaining` if given.
     */
    private function extractTag(string $html, string $attribute, string $alsoContaining = ''): string
    {
        preg_match_all('/<[a-z]+[^>]*' . preg_quote($attribute, '/') . '="[^"]*"[^>]*>/', $html, $matches);
        $tags = array_values(array_filter(
            $matches[0],
            static fn(string $tag): bool => $alsoContaining === '' || str_contains($tag, $alsoContaining),
        ));
        $this->assertCount(1, $tags, sprintf('Expected exactly one element with %s %s.', $attribute, $alsoContaining));

        return $tags[0];
    }
}
