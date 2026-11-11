<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Enum\PaginationType;
use Jramke\FluidPrimitives\Service\PaginationLinkBuilder;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use Jramke\FluidPrimitives\Utility\PaginationRange;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

// A context with a getter per value the parts read
// @mago-expect lint:too-many-methods
#[Autoconfigure(public: true)]
class PaginationContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    private const int DEFAULT_PAGE_SIZE = 10;

    private bool $rendersPagesOnClient = false;

    public function __construct(
        private readonly TranslatorService $translator,
        private readonly PaginationLinkBuilder $linkBuilder,
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

    public function afterRendering(string &$html): void
    {
        $this->rendersPagesOnClient = str_contains($html, sprintf(
            'data-pagination-item-template="%s"',
            htmlspecialchars(Typed::string($this->get('rootId'))),
        ));
    }

    /**
     * The URL of every page, for links that the client renders from the item template: a URL with a
     * cHash can only come from the server.
     *
     * @return array<int, string>|null By page
     */
    #[ExposeToClient(excludeIfNull: true)]
    public function getPageUrls(): ?array
    {
        if (!$this->rendersPagesOnClient || !$this->isLink()) {
            return null;
        }

        // ponytail: one typolink per page on an uncached render; send a window of pages instead if
        // lists with thousands of pages need it
        $urls = [];
        foreach (range(1, max(1, $this->getTotalPages())) as $page) {
            $url = $this->getPageUrl($page);
            if ($url !== null) {
                $urls[$page] = $url;
            }
        }

        return $urls;
    }

    public function isLink(): bool
    {
        return Typed::stringOrNull(EnumUtility::normalize($this->get('type'))) === PaginationType::Link->value;
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
            // An item of a template has no page yet
            'url' => $value > 0 ? $this->getPageUrl($value) : null,
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

    /**
     * The URL of `$page` with `type` `link`, see {@see PaginationLinkBuilder::build()}.
     */
    public function getPageUrl(int $page): ?string
    {
        return $this->isLink()
            ? $this->linkBuilder->build(
                $this->getRequest(),
                Typed::stringOrNull($this->get('pageArgument')) ?? 'currentPage',
                $page,
                Typed::stringOrNull($this->get('section')),
            )
            : null;
    }

    private function getPaginator(): ?PaginatorInterface
    {
        // @mago-expect analysis:mixed-assignment
        $paginator = $this->get('paginator');

        return $paginator instanceof PaginatorInterface ? $paginator : null;
    }
}
