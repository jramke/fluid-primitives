<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SliderRenderingTest extends FunctionalTestCase
{
    private const string THUMB_ALIGNMENT_CENTER = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\SliderThumbAlignment::Center')}";
    private const string ORIGIN_CENTER = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\SliderOrigin::Center')}";
    private const string ORIGIN_END = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\SliderOrigin::End')}";
    private const string ORIENTATION_VERTICAL = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\Orientation::Vertical')}";

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function singleThumbDefaultValues(): iterable
    {
        yield 'a list' => ['{0: 30}'];
        yield 'a bare number' => ['30'];
    }

    #[Test]
    #[DataProvider('singleThumbDefaultValues')]
    public function rendersTheThumbFromTheDefaultValueAndShipsItToTheClientAsAList(string $defaultValue): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="' . $defaultValue . '" min="0" max="50">
                <primitives:slider.valueText />
                <primitives:slider.thumb index="0" />
            </primitives:slider.root>
        ');

        $rootId = (string)$this->elementWith($html, 'data-slider-root')->get('data-slider-root');
        $thumb = $this->elementWith($html, 'data-slider-thumb');
        $this->assertSame($rootId, $thumb->get('data-slider-thumb'));
        $this->assertSame('slider', $thumb->get('role'));
        $this->assertSame('30', $thumb->get('aria-valuenow'));
        $this->assertSame('0', $thumb->get('aria-valuemin'));
        $this->assertSame('50', $thumb->get('aria-valuemax'));
        $this->assertMatchesRegularExpression('/data-slider-value-text="[^"]*"[^>]*>\s*30\s*</', $html);

        // the machine takes a list of numbers, whichever of the two the template was given
        $props = HydrationRegistry::getInstance()->get('primitives', 'slider', $rootId)['props'];
        $this->assertSame([30.0], $props['defaultValue']);
    }

    #[Test]
    public function constrainsEachThumbToItsNeighborsWithTheGapBetweenThem(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 20, 1: 60}" min="0" max="100" step="1" minStepsBetweenThumbs="5">
                <primitives:slider.thumb index="0" />
                <primitives:slider.thumb index="1" />
            </primitives:slider.root>
        ');

        $thumbs = $this->elementsByValue($html, 'data-slider-thumb');
        $this->assertSame([0, 1], array_keys($thumbs));
        // the first thumb may not pass the second one's value (60) minus the gap (step * minStepsBetweenThumbs = 5) ...
        $this->assertSame(['0', '55', '20'], [
            $thumbs[0]->get('aria-valuemin'),
            $thumbs[0]->get('aria-valuemax'),
            $thumbs[0]->get('aria-valuenow'),
        ]);
        // ... and the second one not the first one's value (20) plus the gap
        $this->assertSame(['25', '100', '60'], [
            $thumbs[1]->get('aria-valuemin'),
            $thumbs[1]->get('aria-valuemax'),
            $thumbs[1]->get('aria-valuenow'),
        ]);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function disabledStates(): iterable
    {
        yield 'enabled' => ['', false];
        yield 'disabled' => ['disabled="{true}"', true];
    }

    #[Test]
    #[DataProvider('disabledStates')]
    public function makesTheThumbFocusableUnlessTheSliderIsDisabled(string $arguments, bool $disabled): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 10}" ' .
        $arguments .
        '>
                <primitives:slider.thumb index="0" />
            </primitives:slider.root>
        ');

        $thumb = $this->elementWith($html, 'data-slider-thumb');
        $this->assertSame($disabled ? 'true' : 'false', $thumb->get('aria-disabled'));
        $this->assertSame($disabled, $thumb->has('data-disabled'));
        // focusable before the client has hydrated, so the keyboard reaches it
        $this->assertSame($disabled ? null : '0', $thumb->get('tabindex'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function thumbAlignments(): iterable
    {
        yield 'contained by default' => ['', true];
        // only a thumb that is centered on its value needs no measuring of its size
        yield 'centered' => ['thumbAlignment="' . self::THUMB_ALIGNMENT_CENTER . '"', false];
    }

    #[Test]
    #[DataProvider('thumbAlignments')]
    public function hidesThumbsAndMarkersUntilTheClientMeasuredThemUnlessTheyAreCentered(
        string $arguments,
        bool $hidden,
    ): void {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 30}" ' .
        $arguments .
        '>
                <primitives:slider.markerGroup>
                    <primitives:slider.marker value="50" />
                </primitives:slider.markerGroup>
                <primitives:slider.thumb index="0" />
            </primitives:slider.root>
        ');

        foreach (['data-slider-thumb', 'data-slider-marker'] as $part) {
            $style = (string)$this->elementWith($html, $part)->get('style');
            $this->assertSame($hidden, str_contains($style, 'visibility: hidden;'), $part);
        }
    }

    #[Test]
    public function rendersMarkerStateRelativeToTheCurrentValues(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 30, 1: 70}" min="0" max="100">
                <primitives:slider.markerGroup>
                    <primitives:slider.marker value="10" />
                    <primitives:slider.marker value="50" />
                    <primitives:slider.marker value="90" />
                </primitives:slider.markerGroup>
            </primitives:slider.root>
        ');

        $markers = $this->elementsByValue($html, 'data-slider-marker');
        $this->assertSame(
            ['10' => 'under-value', '50' => 'at-value', '90' => 'over-value'],
            array_map(static fn($marker): ?string => $marker->get('data-state'), $markers),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rangeStyles(): iterable
    {
        yield 'from the start' => ['defaultValue="{0: 30}"', 'position: absolute; left: 0%; right: 70%;'];
        yield 'between two thumbs' => ['defaultValue="{0: 25, 1: 75}"', 'position: absolute; left: 25%; right: 25%;'];
        yield 'from the center to a lower value' => [
            'defaultValue="{0: 30}" origin="' . self::ORIGIN_CENTER . '"',
            'position: absolute; left: 30%; right: 50%;',
        ];
        yield 'from the center to a higher value' => [
            'defaultValue="{0: 70}" origin="' . self::ORIGIN_CENTER . '"',
            'position: absolute; left: 50%; right: 30%;',
        ];
        yield 'from the end' => [
            'defaultValue="{0: 30}" origin="' . self::ORIGIN_END . '"',
            'position: absolute; left: 30%; right: 0%;',
        ];
        yield 'vertical' => [
            'defaultValue="{0: 30}" orientation="' . self::ORIENTATION_VERTICAL . '"',
            'position: absolute; bottom: 0%; top: 70%;',
        ];
    }

    #[Test]
    #[DataProvider('rangeStyles')]
    public function positionsTheRangeFromTheDefaultValueBeforeTheClientMeasuredAnything(
        string $arguments,
        string $style,
    ): void {
        $html = $this->renderTemplate('
            <primitives:slider.root ' . $arguments . '>
                <primitives:slider.control>
                    <primitives:slider.track>
                        <primitives:slider.range />
                    </primitives:slider.track>
                </primitives:slider.control>
            </primitives:slider.root>
        ');

        $this->assertSame($style, $this->elementWith($html, 'data-slider-range')->get('style'));
    }

    #[Test]
    public function rendersOneHiddenInputPerThumbWithItsValueAndTheRootNameAsIs(): void
    {
        // No automatic `[]` suffixing for multi-thumb sliders - same as CheckboxGroup, the consumer
        // writes `name="range[]"` themselves when they want array-style submission.
        $html = $this->renderTemplate('
            <primitives:slider.root name="range[]" defaultValue="{0: 20, 1: 80}">
                <primitives:slider.thumb index="0">
                    <primitives:slider.hiddenInput />
                </primitives:slider.thumb>
                <primitives:slider.thumb index="1">
                    <primitives:slider.hiddenInput />
                </primitives:slider.thumb>
            </primitives:slider.root>
        ');

        $inputs = $this->elementsByValue($html, 'data-slider-hidden-input');
        $this->assertSame([0, 1], array_keys($inputs));
        $this->assertSame(
            ['20', '80'],
            array_values(array_map(static fn($input): ?string => $input->get('value'), $inputs)),
        );
        foreach ($inputs as $input) {
            $this->assertSame('range[]', $input->get('name'));
            // the input only carries the value, the thumb is what the user sees
            $this->assertTrue($input->has('hidden'));
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function valueTexts(): iterable
    {
        yield 'one thumb' => ['{0: 40}', '40'];
        yield 'a range' => ['{0: 25, 1: 75}', '25 - 75'];
    }

    #[Test]
    #[DataProvider('valueTexts')]
    public function rendersTheValueTextTheClientKeepsInTheSameFormat(string $defaultValue, string $text): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="' . $defaultValue . '">
                <primitives:slider.valueText />
            </primitives:slider.root>
        ');

        $this->assertMatchesRegularExpression(
            '/data-slider-value-text="[^"]*"[^>]*>\s*' . preg_quote($text, '/') . '\s*</',
            $html,
        );
    }

    #[Test]
    public function takesNameAndStateFromTheSurroundingField(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="price" rootId="my-field" disabled="{true}" invalid="{true}">
                <primitives:slider.root>
                    <primitives:slider.label>Price</primitives:slider.label>
                    <primitives:slider.thumb index="0">
                        <primitives:slider.hiddenInput />
                    </primitives:slider.thumb>
                </primitives:slider.root>
            </primitives:field.root>
        ');

        $root = $this->elementWith($html, 'data-slider-root');
        $this->assertTrue($root->has('data-disabled'));
        $this->assertTrue($root->has('data-invalid'));
        $this->assertSame('true', $this->elementWith($html, 'data-slider-thumb')->get('aria-disabled'));
        $this->assertSame('price', $this->elementWith($html, 'data-slider-hidden-input')->get('name'));
        // The Field's label id reaches `slider.label` through `ids` as-is - the part names already match.
        $this->assertSame('field:my-field:label', $this->elementWith($html, 'data-slider-label')->get('id'));
    }

    #[Test]
    public function rendersTheDraggingIndicatorHiddenWithTheThumbValueAsContent(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 40}">
                <primitives:slider.thumb index="0">
                    <primitives:slider.draggingIndicator />
                </primitives:slider.thumb>
            </primitives:slider.root>
        ');

        $this->assertTrue($this->elementWith($html, 'data-slider-dragging-indicator')->has('hidden'));
        $this->assertMatchesRegularExpression('/data-slider-dragging-indicator="[^"]*"[^>]*>\s*40\s*</', $html);
    }
}
