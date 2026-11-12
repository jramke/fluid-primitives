<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Exception\TemplateErrorException;
use Jramke\FluidPrimitives\Pagination\UrlAwarePaginator;
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
    public function buildsTheUrlsOfTheShownPagesWithAUrlAwarePaginatorAndSendsAllOnlyToLinksWithAnItemTemplate(): void
    {
        $builtPages = [];
        $paginator = new UrlAwarePaginator(new ArrayPaginator(range(1, 300), 2, 10), static function (int $page) use (
            &$builtPages,
        ): string {
            $builtPages[] = $page;
            return '/events?page=' . $page;
        });
        $props = static fn(): array => array_values(
            HydrationRegistry::getInstance()->getAll()['primitives']['pagination'],
        )[0]['props'];

        $html = $this->renderTemplate($this->pagination('paginator="{paginator}"', 1, 2, 3), [
            'paginator' => $paginator,
        ]);

        $items = $this->elementsByValue($html, 'data-pagination-item');
        $this->assertSame('/events?page=3', $items['3']->get('href'));
        $this->assertFalse($items['3']->has('type'));
        $this->assertSame('/events?page=1', $this->elementWith($html, 'data-pagination-prev-trigger')->get('href'));
        $this->assertSame('link', $props()['type']);
        $this->assertArrayNotHasKey('pageUrls', $props());
        $this->assertNotContains(15, $builtPages);

        $this->renderTemplate('<primitives:pagination.root paginator="{paginator}"><primitives:pagination.itemTemplate>' .
        '<primitives:pagination.item /></primitives:pagination.itemTemplate></primitives:pagination.root>', [
            'paginator' => $paginator,
        ]);
        $this->assertCount(30, $props()['pageUrls']);
        $this->assertSame('/events?page=15', $props()['pageUrls'][15]);

        $this->renderTemplate(
            '<primitives:pagination.root count="300"><primitives:pagination.itemTemplate>' .
            '<primitives:pagination.item /></primitives:pagination.itemTemplate></primitives:pagination.root>',
        );
        $this->assertSame('button', $props()['type']);
        $this->assertArrayNotHasKey('pageUrls', $props());
    }

    #[Test]
    public function rendersThePagesOfItsContextInALoop(): void
    {
        $html = $this->renderTemplate(
            '<primitives:pagination.root count="120" defaultPage="6">' .
            '<ui:context name="primitives:pagination" as="paginationContext" />' .
            '<f:for each="{paginationContext.pages}" as="page"><f:if condition="{page.type} == \'page\'">' .
            '<f:then><primitives:pagination.item value="{page.value}" /></f:then>' .
            '<f:else><primitives:pagination.ellipsis index="{page.index}" /></f:else>' .
            '</f:if></f:for></primitives:pagination.root>',
        );

        $this->assertSame(
            ['1', '5', '6', '7', '12'],
            array_map(strval(...), array_keys($this->elementsByValue($html, 'data-pagination-item'))),
        );
        $this->assertSame(
            ['1', '5'],
            array_map(
                static fn($ellipsis): ?string => $ellipsis->get('data-index'),
                $this->elementsWith($html, 'data-pagination-ellipsis'),
            ),
        );
    }

    #[Test]
    public function leavesOutAScriptUrl(): void
    {
        $html = $this->renderTemplate($this->pagination('count="30" pageUrls="{pageUrls}"', 1, 2), [
            'pageUrls' => ['1' => '/events', '2' => ' javascript:alert(1)'],
        ]);

        $items = $this->elementsByValue($html, 'data-pagination-item');
        $this->assertSame('/events', $items['1']->get('href'));
        $this->assertFalse($items['2']->has('href'));
    }

    #[Test]
    public function rendersLinksToThePageUrlsOfTheProp(): void
    {
        $html = $this->renderTemplate($this->pagination('count="30" defaultPage="2" pageUrls="{pageUrls}"', 3), [
            'pageUrls' => ['1' => '/events', '2' => '/events?page=2', '3' => '/events?page=3'],
        ]);

        $this->assertSame('/events?page=3', $this->elementWith($html, 'data-pagination-item')->get('href'));
        $this->assertSame('/events', $this->elementWith($html, 'data-pagination-prev-trigger')->get('href'));

        $this->renderTemplate($this->pagination('count="30"', 1));
        $props = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['pagination'])[0]['props'];
        $this->assertSame('button', $props['type']);
    }

    #[Test]
    public function rejectsThePropsThePaginatorOverrides(): void
    {
        $this->assertRenderThrows(
            TemplateErrorException::class,
            'Leave out count, defaultPage, pageUrls next to the paginator of pagination.root',
            fn() => $this->renderTemplate(
                $this->pagination('paginator="{paginator}" count="30" defaultPage="2" pageUrls="{pageUrls}"', 1),
                [
                    'paginator' => new UrlAwarePaginator(
                        new ArrayPaginator(range(1, 30), 1, 10),
                        static fn(int $page): string => '/events?page=' . $page,
                    ),
                    'pageUrls' => [1 => '/events'],
                ],
            ),
        );
    }

    #[Test]
    public function acceptsPageUrlsNextToAPaginatorWithoutUrls(): void
    {
        $html = $this->renderTemplate($this->pagination('paginator="{paginator}" pageUrls="{pageUrls}"', 2), [
            'paginator' => new ArrayPaginator(range(1, 30), 1, 10),
            'pageUrls' => [1 => '/events', 2 => '/events?page=2', 3 => '/events?page=3'],
        ]);

        $this->assertSame('/events?page=2', $this->elementWith($html, 'data-pagination-item')->get('href'));
    }

    #[Test]
    public function rejectsPageUrlsKeyedFromZero(): void
    {
        $this->assertRenderThrows(
            TemplateErrorException::class,
            'The keys of the pageUrls prop of pagination.root are the page numbers starting at 1',
            fn() => $this->renderTemplate($this->pagination('count="30" pageUrls="{pageUrls}"', 1), [
                'pageUrls' => ['/events', '/events?page=2', '/events?page=3'],
            ]),
        );
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
