<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\TemplateErrorException;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TocRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersEveryPartAsItsElementWithTheRootId(): void
    {
        $html = $this->renderTemplate(
            '<primitives:toc.root rootId="my-toc">' .
            '<primitives:toc.title>On this page</primitives:toc.title>' .
            '<primitives:toc.list>' .
            '<primitives:toc.indicator />' .
            '<primitives:toc.item value="intro"><primitives:toc.link>Intro</primitives:toc.link></primitives:toc.item>' .
            '</primitives:toc.list>' .
            '</primitives:toc.root>',
        );

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
    public function carriesTheValueAndTheDepthOfAnItemAndLinksToItsHeading(): void
    {
        $html = $this->renderTemplate($this->toc('', ['intro' => null, 'install' => 3, 'a-b' => 4]));

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
        $html = $this->renderTemplate($this->toc('defaultActiveIds="{0: \'b\', 1: \'c\'}"', [
            'a' => null,
            'b' => null,
            'c' => null,
            'd' => null,
        ]));

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
    public function aLinkOutsideAnItemThrows(): void
    {
        $this->assertRenderThrows(
            TemplateErrorException::class,
            'must only be used inside a toc.item component',
            fn() => $this->renderTemplate(
                '<primitives:toc.root><primitives:toc.link>x</primitives:toc.link></primitives:toc.root>',
            ),
        );
    }

    #[Test]
    public function registersOneRootWithItsClientProps(): void
    {
        $this->renderTemplate($this->toc('scrollEl="#content" rootMargin="0px" threshold="0.5" autoScroll="{false}" defaultActiveIds="{0: \'a\'}"', [
            'a' => null,
            'b' => 3,
        ]));

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
    }

    /**
     * @param array<string, int|null> $items heading id => depth (null for the default)
     */
    private function toc(string $rootArguments, array $items): string
    {
        $markup = '';
        foreach ($items as $value => $depth) {
            $markup .=
                '<primitives:toc.item value="' .
                $value .
                '"' .
                ($depth === null ? '' : ' depth="' . $depth . '"') .
                '><primitives:toc.link>' .
                $value .
                '</primitives:toc.link></primitives:toc.item>';
        }

        return (
            '<primitives:toc.root ' .
            $rootArguments .
            '><primitives:toc.list>' .
            $markup .
            '</primitives:toc.list></primitives:toc.root>'
        );
    }
}
