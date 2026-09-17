<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SliderRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersSliderRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root>
                <primitives:slider.control>
                    <primitives:slider.track>
                        <primitives:slider.range />
                    </primitives:slider.track>
                    <primitives:slider.thumb index="0" />
                </primitives:slider.control>
            </primitives:slider.root>
        ');

        $this->assertStringContainsString('data-scope="slider"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersThumbWithAriaValueDerivedFromDefaultValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 30}" min="0" max="50">
                <primitives:slider.thumb index="0" />
            </primitives:slider.root>
        ');

        $this->assertStringContainsString('role="slider"', $html);
        $this->assertStringContainsString('aria-valuenow="30"', $html);
        $this->assertStringContainsString('aria-valuemin="0"', $html);
        $this->assertStringContainsString('aria-valuemax="50"', $html);
    }

    #[Test]
    public function acceptsDefaultValueAsABareNumberForASingleThumb(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="30" min="0" max="50">
                <primitives:slider.thumb index="0" />
                <primitives:slider.valueText />
            </primitives:slider.root>
        ');

        $this->assertStringContainsString('aria-valuenow="30"', $html);
        $this->assertMatchesRegularExpression('/data-part="value-text"[^>]*>\s*30\s*</', $html);
    }

    #[Test]
    public function constrainsThumbRangeToNeighboringThumbsWithGap(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 20, 1: 60}" min="0" max="100" step="1" minStepsBetweenThumbs="5">
                <primitives:slider.thumb index="0" />
                <primitives:slider.thumb index="1" />
            </primitives:slider.root>
        ');

        preg_match_all('/<div\s+role="slider".*?>/s', $html, $matches);
        [$thumb0, $thumb1] = $matches[0];

        // thumb 0's max is bounded by thumb 1's value (60) minus the gap (step * minStepsBetweenThumbs = 5)
        $this->assertStringContainsString('data-index="0"', $thumb0);
        $this->assertStringContainsString('aria-valuemax="55"', $thumb0);
        // thumb 1's min is bounded by thumb 0's value (20) plus the gap
        $this->assertStringContainsString('data-index="1"', $thumb1);
        $this->assertStringContainsString('aria-valuemin="25"', $thumb1);
    }

    #[Test]
    public function omitsTabindexAndMarksAriaDisabledWhenDisabled(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root disabled="{true}" defaultValue="{0: 10}">
                <primitives:slider.thumb index="0" />
            </primitives:slider.root>
        ');

        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertDoesNotMatchRegularExpression('/tabindex/', $html);
    }

    #[Test]
    public function rendersMarkerStateRelativeToCurrentValue(): void
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

        $this->assertMatchesRegularExpression('/data-value="10"[^>]*data-state="under-value"/', $html);
        $this->assertMatchesRegularExpression('/data-value="50"[^>]*data-state="at-value"/', $html);
        $this->assertMatchesRegularExpression('/data-value="90"[^>]*data-state="over-value"/', $html);
    }

    #[Test]
    public function rendersHiddenInputInheritingIndexAndValueFromWrappingThumb(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root name="price" defaultValue="{0: 42}">
                <primitives:slider.thumb index="0">
                    <primitives:slider.hiddenInput />
                </primitives:slider.thumb>
            </primitives:slider.root>
        ');

        $this->assertStringContainsString('data-part="hidden-input"', $html);
        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('name="price"', $html);

        preg_match('/<input\s+type="text".*?\/>/s', $html, $matches);
        $this->assertStringContainsString('hidden', $matches[0]);
        $this->assertStringContainsString('data-part="hidden-input"', $matches[0]);
    }

    #[Test]
    public function usesTheRootNameAsIsForEveryThumbWithoutAutomaticSuffixing(): void
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

        $this->assertMatchesRegularExpression('/name="range\[\]"/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="range\[\]\[\]"/', $html);
    }

    #[Test]
    public function rendersValueTextFallbackForSingleAndMultipleThumbs(): void
    {
        $singleHtml = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 40}">
                <primitives:slider.valueText />
            </primitives:slider.root>
        ');
        $this->assertMatchesRegularExpression('/data-part="value-text"[^>]*>\s*40\s*</', $singleHtml);

        $rangeHtml = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 25, 1: 75}">
                <primitives:slider.valueText />
            </primitives:slider.root>
        ');
        $this->assertMatchesRegularExpression('/data-part="value-text"[^>]*>\s*25 - 75\s*</', $rangeHtml);
    }

    #[Test]
    public function propagatesNameAndStateFromField(): void
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

        $thumbTag = $this->extractTag($html, 'thumb');
        $this->assertStringContainsString('aria-disabled="true"', $thumbTag);

        $this->assertStringContainsString('name="price"', $html);

        // Field.Label generates its own id independently of the nested Slider - the primitive's
        // own `slider.label` part inherits it directly since its ref name already matches.
        $labelTag = $this->extractTag($html, 'label');
        $this->assertStringContainsString('id="field:my-field:label"', $labelTag);
    }

    #[Test]
    public function rendersDraggingIndicatorHiddenWithThumbValueAsFallbackContent(): void
    {
        $html = $this->renderTemplate('
            <primitives:slider.root defaultValue="{0: 40}">
                <primitives:slider.thumb index="0">
                    <primitives:slider.draggingIndicator />
                </primitives:slider.thumb>
            </primitives:slider.root>
        ');

        preg_match('/<div\s+role="presentation".*?>\s*40\s*<\/div>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'Expected the dragging indicator div to contain the thumb value.');
        $this->assertStringContainsString('hidden', $matches[0]);
        $this->assertStringContainsString('data-part="dragging-indicator"', $matches[0]);
    }

    private function extractTag(string $html, string $part): string
    {
        $matched = preg_match('/<[a-z]+[^>]*data-part="' . preg_quote($part, '/') . '"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with data-part="%s".', $part));

        return $matches[0];
    }
}
