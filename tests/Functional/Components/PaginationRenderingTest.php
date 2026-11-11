<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;

final class PaginationRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function marksTheCurrentPageAndDisablesTheTriggersThatLeadNowhere(): void
    {
        $html = $this->renderTemplate($this->pagination('count="120" defaultPage="1"', 1, 2));

        $this->assertSame('Pagination', $this->elementWith($html, 'data-pagination-root')->get('aria-label'));

        $items = $this->elementsByValue($html, 'data-pagination-item');
        $this->assertSame('page', $items['1']->get('aria-current'));
        $this->assertTrue($items['1']->has('data-selected'));
        $this->assertFalse($items['2']->has('aria-current'));
        $this->assertFalse($items['2']->has('data-selected'));
        $this->assertSame('Page 2', $items['2']->get('aria-label'));
        $this->assertSame('button', $items['2']->get('type'));

        foreach (['first', 'prev'] as $trigger) {
            $element = $this->elementWith($html, 'data-pagination-' . $trigger . '-trigger');
            $this->assertTrue($element->has('disabled'), $trigger);
            $this->assertTrue($element->has('data-disabled'), $trigger);
        }
        foreach (['next', 'last'] as $trigger) {
            $element = $this->elementWith($html, 'data-pagination-' . $trigger . '-trigger');
            $this->assertFalse($element->has('disabled'), $trigger);
            $this->assertFalse($element->has('data-disabled'), $trigger);
        }
    }

    #[Test]
    public function takesThePageAndTheNumberOfPagesFromAPaginator(): void
    {
        $paginator = new ArrayPaginator(range(1, 95), 10, 10);

        $html = $this->renderTemplate($this->pagination('paginator="{paginator}"', 9, 10), ['paginator' => $paginator]);

        $items = $this->elementsByValue($html, 'data-pagination-item');
        $this->assertSame('page', $items['10']->get('aria-current'));
        $this->assertFalse($items['9']->has('aria-current'));
        $this->assertTrue($this->elementWith($html, 'data-pagination-next-trigger')->has('disabled'));
        $this->assertFalse($this->elementWith($html, 'data-pagination-prev-trigger')->has('disabled'));

        // A paginator has no public total, the machine gets one that makes the same number of pages
        $props = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['pagination'])[0]['props'];
        $this->assertSame(10, $props['defaultPage']);
        $this->assertSame(100, $props['count']);
        $this->assertSame(10, $props['defaultPageSize']);
    }

    #[Test]
    public function sendsThePageUrlsOnlyForLinksTheClientRendersFromTheItemTemplate(): void
    {
        $link = 'type="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\PaginationType::Link\')}"';
        $template = '<ui:template name="itemTemplate" context="pagination"><primitives:pagination.item /></ui:template>';
        $props = static fn(): array => array_values(
            HydrationRegistry::getInstance()->getAll()['primitives']['pagination'],
        )[0]['props'];

        $this->renderTemplate($this->pagination('count="30" ' . $link, 1));
        $this->assertArrayNotHasKey('pageUrls', $props());

        $this->renderTemplate(
            '<primitives:pagination.root count="30" ' . $link . '>' . $template . '</primitives:pagination.root>',
        );
        // Typolink has no site to link to here, so the map stays empty: only whether it is sent is tested
        $this->assertArrayHasKey('pageUrls', $props());

        $this->renderTemplate('<primitives:pagination.root count="30">' . $template . '</primitives:pagination.root>');
        $this->assertArrayNotHasKey('pageUrls', $props());
    }

    private function pagination(string $rootArguments, int ...$pages): string
    {
        $items = implode('', array_map(
            static fn(int $page): string => '<primitives:pagination.item value="' . $page . '" />',
            $pages,
        ));

        return (
            '<primitives:pagination.root ' .
            $rootArguments .
            '>' .
            '<primitives:pagination.firstTrigger>First</primitives:pagination.firstTrigger>' .
            '<primitives:pagination.prevTrigger>Previous</primitives:pagination.prevTrigger>' .
            $items .
            '<primitives:pagination.nextTrigger>Next</primitives:pagination.nextTrigger>' .
            '<primitives:pagination.lastTrigger>Last</primitives:pagination.lastTrigger>' .
            '</primitives:pagination.root>'
        );
    }
}
