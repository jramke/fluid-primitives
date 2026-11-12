<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Enum\PaginationType;
use Jramke\FluidPrimitives\Pagination\UrlAwarePaginator;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\PaginationRange;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

// A context with a getter per value the parts read
// @mago-expect lint:too-many-methods
// @mago-expect lint:cyclomatic-complexity
class PaginationContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    private const int DEFAULT_PAGE_SIZE = 10;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    /**
     * @return array{rootLabel: string, firstTriggerLabel: string, prevTriggerLabel: string, nextTriggerLabel: string, lastTriggerLabel: string, itemLabel: string}
     */
    #[ExposeToClient]
    public function getTranslations(): array
    {
        // translationsWithDefaults() merges user overrides, so it can't be typed more precisely than array.
        // @mago-expect analysis:invalid-return-statement
        return $this->translationsWithDefaults([
            'rootLabel' => 'pagination.rootLabel',
            'firstTriggerLabel' => 'pagination.firstTriggerLabel',
            'prevTriggerLabel' => 'pagination.prevTriggerLabel',
            'nextTriggerLabel' => 'pagination.nextTriggerLabel',
            'lastTriggerLabel' => 'pagination.lastTriggerLabel',
            'itemLabel' => 'pagination.itemLabel',
        ]);
    }

    /**
     * A paginator has no public total, so with one the count is the number of pages times the page
     * size: the machine needs it only for the number of pages.
     */
    #[ExposeToClient]
    public function getCount(): int
    {
        $count = Typed::intOrNull($this->get('count'));
        if ($count !== null) {
            return max(0, $count);
        }

        $paginator = $this->getPaginator();

        return $paginator instanceof PaginatorInterface ? $paginator->getNumberOfPages() * $this->getPageSize() : 0;
    }

    #[ExposeToClient(name: 'defaultPage')]
    public function getPage(): int
    {
        return $this->getPaginator()?->getCurrentPageNumber() ?? max(
            1,
            Typed::intOrNull($this->get('defaultPage')) ?? 1,
        );
    }

    public function getPageSize(): int
    {
        return max(1, Typed::intOrNull($this->get('defaultPageSize')) ?? self::DEFAULT_PAGE_SIZE);
    }

    public function getTotalPages(): int
    {
        return (int)ceil($this->getCount() / $this->getPageSize());
    }

    /**
     * The URL of every page, for the links the client renders from the `itemTemplate`: from the
     * `pageUrls` prop or a {@see UrlAwarePaginator}. Pages that reload with every click need them only
     * in the HTML.
     *
     * @return array<int, string>|null By page
     */
    #[ExposeToClient(excludeIfNull: true)]
    public function getPageUrls(): ?array
    {
        $renderedTemplates = Typed::arrayOrNull($this->get(Constants::RENDERED_TEMPLATES)) ?? [];
        if (!$this->isLink() || !array_key_exists('itemTemplate', $renderedTemplates)) {
            return null;
        }

        // ponytail: one URL per page on an uncached render; send a window of pages instead if lists
        // with thousands of pages need it (a URL pattern would break with the cHash)
        $pageUrls = [];
        foreach (range(1, max(1, $this->getTotalPages())) as $page) {
            $url = $this->getPageUrl($page);
            if ($url !== null) {
                $pageUrls[$page] = $url;
            }
        }

        return $pageUrls;
    }

    #[ExposeToClient]
    public function getType(): PaginationType
    {
        return $this->isLink() ? PaginationType::Link : PaginationType::Button;
    }

    public function isLink(): bool
    {
        return $this->getPaginator() instanceof UrlAwarePaginator || $this->getPageUrlsProp() !== null;
    }

    /**
     * The entries the pagination shows between its triggers, the same list the machine computes.
     *
     * @return list<object{type: 'page'|'ellipsis', value: int|null, index: int}>
     */
    public function getPages(): array
    {
        $pages = PaginationRange::compute(
            $this->getPage(),
            $this->getTotalPages(),
            max(0, Typed::intOrNull($this->get('siblingCount')) ?? 1),
            max(0, Typed::intOrNull($this->get('boundaryCount')) ?? 1),
        );

        $entries = [];
        foreach ($pages as $index => $page) {
            $entries[] = (object)[
                'type' => $page === null ? 'ellipsis' : 'page',
                'value' => $page,
                'index' => $index,
            ];
        }

        return $entries;
    }

    /**
     * @param array{value: int|string} $item
     * @return object{current: bool, label: string, url: string|null}
     */
    public function getItemState(array $item): object
    {
        $value = (int)$item['value'];

        return (object)[
            'current' => $value === $this->getPage(),
            'label' => str_replace(
                ['%page%', '%totalPages%'],
                [(string)$value, (string)$this->getTotalPages()],
                $this->getTranslations()['itemLabel'],
            ),
            'url' => $this->getPageUrl($value),
        ];
    }

    /**
     * Mirrors the trigger props of the machine: the first and last trigger link to their page even
     * while disabled, the previous and next trigger only when that page exists.
     *
     * @param 'first'|'prev'|'next'|'last' $trigger
     * @return object{disabled: bool, url: string|null}
     */
    public function getTriggerState(string $trigger): object
    {
        $page = $this->getPage();
        $totalPages = $this->getTotalPages();
        $isFirstPage = $page === 1;
        $isLastPage = $page >= $totalPages;

        [$disabled, $target] = match ($trigger) {
            'first' => [$isFirstPage, 1],
            'prev' => [$isFirstPage, $isFirstPage ? null : $page - 1],
            'next' => [$isLastPage, $isLastPage ? null : $page + 1],
            'last' => [$isLastPage, $totalPages],
        };

        return (object)[
            'disabled' => $disabled,
            'url' => $target === null ? null : $this->getPageUrl($target),
        ];
    }

    public function getPageUrl(int $page): ?string
    {
        if ($page < 1 || $page > $this->getTotalPages()) {
            return null;
        }

        $paginator = $this->getPaginator();
        $url = $paginator instanceof UrlAwarePaginator
            ? $paginator->getPageUrl($page)
            : Typed::stringOrNull($this->getPageUrlsProp()[$page] ?? null);

        // A script URL is left out, as in every other attribute the library renders
        return $url === null || $url === '' || TagAttributes::isScriptUrl('href', $url) ? null : $url;
    }

    /**
     * The `pageUrls` prop is keyed by page number: a list from 0 would link every page to the next one.
     */
    public function isPageUrlsKeyedByPage(): bool
    {
        foreach (array_keys($this->getPageUrlsProp() ?? []) as $key) {
            if ($key < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>|null
     */
    private function getPageUrlsProp(): ?array
    {
        // A Fluid array of the template carries no type
        // @mago-expect analysis:less-specific-return-statement
        return Typed::arrayOrNull($this->get('pageUrls')) ?: null;
    }

    private function getPaginator(): ?PaginatorInterface
    {
        // @mago-expect analysis:mixed-assignment
        $paginator = $this->get('paginator');

        return $paginator instanceof PaginatorInterface ? $paginator : null;
    }
}
