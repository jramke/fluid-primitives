<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Pagination;

use Closure;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * Wraps any paginator, so the pages of `ui:pagination.root` are links. `$pageUrl` returns the URL of
 * a page, e.g. built with the `UriBuilder` of your controller. The root builds the URLs of the pages
 * it shows, and all of them only when the client renders links from an `itemTemplate`.
 */
class UrlAwarePaginator implements PaginatorInterface
{
    /** @var array<int, string> */
    private array $pageUrls = [];

    /**
     * @param Closure(int): string $pageUrl
     */
    public function __construct(
        private PaginatorInterface $paginator,
        private readonly Closure $pageUrl,
    ) {}

    public function getPageUrl(int $page): string
    {
        return $this->pageUrls[$page] ??= ($this->pageUrl)($page);
    }

    /**
     * @return array<int, string> The URL of every page, by page number
     */
    public function getPageUrls(): array
    {
        $pageUrls = [];
        foreach (range(1, max(1, $this->getNumberOfPages())) as $page) {
            $pageUrls[$page] = $this->getPageUrl($page);
        }

        return $pageUrls;
    }

    public function withItemsPerPage(int $itemsPerPage): PaginatorInterface
    {
        $clone = clone $this;
        $clone->paginator = $this->paginator->withItemsPerPage($itemsPerPage);
        $clone->pageUrls = [];

        return $clone;
    }

    public function withCurrentPageNumber(int $currentPageNumber): PaginatorInterface
    {
        $clone = clone $this;
        $clone->paginator = $this->paginator->withCurrentPageNumber($currentPageNumber);

        return $clone;
    }

    public function getPaginatedItems(): iterable
    {
        return $this->paginator->getPaginatedItems();
    }

    public function getNumberOfPages(): int
    {
        return $this->paginator->getNumberOfPages();
    }

    public function getCurrentPageNumber(): int
    {
        return $this->paginator->getCurrentPageNumber();
    }

    public function getKeyOfFirstPaginatedItem(): int
    {
        return $this->paginator->getKeyOfFirstPaginatedItem();
    }

    public function getKeyOfLastPaginatedItem(): int
    {
        return $this->paginator->getKeyOfLastPaginatedItem();
    }
}
