<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\TemplateErrorException;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TocRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersEveryPartAsItsElementWithTheRootId(): void
    {
        $html = $this->renderTemplate('<primitives:toc.root rootId="my-toc" items="{items}">' .
        '<primitives:toc.title>On this page</primitives:toc.title>' .
        '<primitives:toc.list>' .
        '<primitives:toc.indicator />' .
        '<primitives:toc.item value="intro"><primitives:toc.link>Intro</primitives:toc.link></primitives:toc.item>' .
        '</primitives:toc.list>' .
        '</primitives:toc.root>', ['items' => [['value' => 'intro', 'depth' => 2]]]);

        $tags = [
            'root' => 'nav',
            'title' => 'h2',
            'list' => 'ul',
            'indicator' => 'div',
            'item' => 'li',
            'link' => 'a',
        ];
        foreach ($tags as $part => $tag) {
            $element = $this->elementWith($html, 'data-toc-' . $part);
            $this->assertSame($tag, $element->tag, $part);
            $this->assertSame('my-toc', $element->get('data-toc-' . $part), $part);
        }
        // hidden until the machine has a rect to point at
        $this->assertTrue($this->elementWith($html, 'data-toc-indicator')->has('hidden'));
    }

    #[Test]
    public function rendersTheTitleAsTheHeadingOfItsLevel(): void
    {
        $html = $this->renderTemplate(
            '<primitives:toc.root items="{items}"><primitives:toc.title level="4">Contents</primitives:toc.title></primitives:toc.root>',
            ['items' => []],
        );

        $this->assertSame('h4', $this->elementWith($html, 'data-toc-title')->tag);
        $this->assertStringContainsString('</h4>', $html);
    }

    #[Test]
    public function carriesTheValueAndTheDepthOfAnItemAndLinksToItsHeading(): void
    {
        $html = $this->renderToc('', ['intro' => 2, 'install' => 3, 'a-b' => 4]);

        $items = $this->elementsByValue($html, 'data-toc-item');
        $this->assertSame(['intro', 'install', 'a-b'], array_keys($items));
        $this->assertSame(
            ['2', '3', '4'],
            array_map(static fn($item) => $item->get('data-depth'), array_values($items)),
        );
        $this->assertStringContainsString('--depth: 3', (string)$items['install']->get('style'));

        $links = $this->elementsByValue($html, 'data-toc-link');
        $this->assertSame('#install', $links['install']->get('href'));
    }

    #[Test]
    public function marksTheDefaultActiveItemsWithTheirPositionAmongThem(): void
    {
        $html = $this->renderToc('defaultActiveIds="{0: \'b\', 1: \'c\'}"', [
            'a' => 2,
            'b' => 2,
            'c' => 2,
            'd' => 2,
        ]);

        $states = [];
        foreach (['data-toc-item', 'data-toc-link'] as $attribute) {
            foreach ($this->elementsByValue($html, $attribute) as $value => $element) {
                $states[$attribute][$value] = [
                    $element->has('data-active'),
                    $element->has('data-first'),
                    $element->has('data-last'),
                ];
            }
        }

        $this->assertSame([false, false, false], $states['data-toc-item']['a']);
        $this->assertSame([true, true, false], $states['data-toc-item']['b']);
        $this->assertSame([true, false, true], $states['data-toc-item']['c']);
        $this->assertSame([false, false, false], $states['data-toc-item']['d']);

        $links = $this->elementsByValue($html, 'data-toc-link');
        $this->assertSame('location', $links['b']->get('aria-current'));
        $this->assertTrue($links['c']->has('data-active'));
        $this->assertFalse($links['a']->has('aria-current'));
        // the link only knows whether its item is active
        $this->assertFalse($links['b']->has('data-first'));
    }

    #[Test]
    public function anItemThatIsNotInTheItemsOfTheRootThrows(): void
    {
        $this->assertRenderThrows(
            InvalidPropValueException::class,
            'The toc item "missing" is not one of the items',
            fn() => $this->renderTemplate('<primitives:toc.root items="{items}">' .
            '<primitives:toc.item value="missing" /></primitives:toc.root>', [
                'items' => [['value' => 'intro', 'depth' => 2]],
            ]),
        );
    }

    #[Test]
    public function aLinkOutsideAnItemThrows(): void
    {
        $this->assertRenderThrows(
            TemplateErrorException::class,
            'must only be used inside a toc.item component',
            fn() => $this->renderTemplate('<primitives:toc.root items="{items}"><primitives:toc.link>x</primitives:toc.link></primitives:toc.root>', ['items' => []]),
        );
    }

    #[Test]
    public function registersOneRootWithItsClientProps(): void
    {
        $this->renderToc('scrollEl="#content" rootMargin="0px" threshold="0.5" autoScroll="{false}" defaultActiveIds="{0: \'a\'}"', [
            'a' => 2,
            'b' => 3,
        ]);

        $tocs = HydrationRegistry::getInstance()->getAll()['primitives']['toc'];

        // the items are parts of the root, not roots of their own
        $this->assertCount(1, $tocs);
        $props = array_values($tocs)[0]['props'];
        $this->assertSame('#content', $props['scrollEl']);
        $this->assertSame('0px', $props['rootMargin']);
        $this->assertEqualsWithDelta(0.5, $props['threshold'], 0.0001);
        $this->assertFalse($props['autoScroll']);
        $this->assertSame('smooth', $props['scrollBehavior']);
        $this->assertSame(['a'], $props['defaultActiveIds']);
        $this->assertSame([['value' => 'a', 'depth' => 2], ['value' => 'b', 'depth' => 3]], $props['items']);
    }

    /**
     * @param array<string, int> $items heading id => depth
     */
    private function renderToc(string $rootArguments, array $items): string
    {
        $entries = [];
        $markup = '';
        foreach ($items as $value => $depth) {
            $entries[] = ['value' => $value, 'depth' => $depth];
            $markup .=
                '<primitives:toc.item value="' .
                $value .
                '"><primitives:toc.link>' .
                $value .
                '</primitives:toc.link></primitives:toc.item>';
        }

        return $this->renderTemplate('<primitives:toc.root items="{items}" ' .
        $rootArguments .
        '><primitives:toc.list>' .
        $markup .
        '</primitives:toc.list></primitives:toc.root>', ['items' => $entries]);
    }
}
