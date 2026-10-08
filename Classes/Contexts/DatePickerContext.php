<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Enum\DatePickerView;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\DateUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class DatePickerContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    // The machine opens an `inline` date picker whatever `defaultOpen` says, so the server renders
    // the same state and the calendar does not flash hidden until hydration.
    public function getOpen(): bool
    {
        return Typed::bool($this->get('inline')) || Typed::bool($this->get('defaultOpen'));
    }

    public function getState(): string
    {
        return $this->getOpen() ? 'open' : 'closed';
    }

    public function getEmpty(): bool
    {
        return $this->getDefaultValue() === null;
    }

    /**
     * The view the calendar starts in: `defaultView`, kept between `minView` and `maxView` like the
     * machine does.
     */
    public function getInitialView(): string
    {
        $views = array_map(static fn(DatePickerView $view): string => $view->value, DatePickerView::cases());
        $indexOf = function (string $prop, int $fallback) use ($views): int {
            $index = array_search(Typed::stringOrNull($this->get($prop)), $views, strict: true);

            return $index === false ? $fallback : $index;
        };

        return $views[min(
            max($indexOf('defaultView', fallback: 0), $indexOf('minView', fallback: 0)),
            $indexOf('maxView', fallback: count($views) - 1),
        )];
    }

    /**
     * One entry per month the day view shows next to each other, counted from `0`.
     *
     * @return list<int>
     */
    public function getMonthOffsets(): array
    {
        return range(0, max(Typed::int($this->get('numOfMonths')), 1) - 1);
    }

    // excludeIfNull: without an own locale the site language applies, and without one of those
    // Zag falls back to its own default.
    #[ExposeToClient(excludeIfNull: true)]
    public function getLocale(): ?string
    {
        $locale = Typed::stringOrNull($this->get('locale')) ?? $this->translator->getLocale($this->getRequest());

        return $locale === null || $locale === '' ? null : $locale;
    }

    #[ExposeToClient(excludeIfNull: true)]
    /**
     * ISO dates (`Y-m-d`), always as a list: the machine takes a list for every selection mode.
     * Accepts what a Field bound to an object hands over - a `DateTimeInterface`, a list of them or
     * date strings - and drops anything else.
     *
     * @return list<string>|null
     */
    public function getDefaultValue(): ?array
    {
        // `defaultValue` is declared type="mixed" and genuinely accepts every shape handled below.
        // @mago-expect analysis:mixed-assignment
        $defaultValue = $this->get('defaultValue');

        $dates = [];
        // @mago-expect analysis:mixed-assignment
        foreach (is_array($defaultValue) ? $defaultValue : [$defaultValue] as $date) {
            $isoDate = DateUtility::toIsoDate($date);
            if ($isoDate !== null) {
                $dates[] = $isoDate;
            }
        }

        return $dates === [] ? null : $dates;
    }

    /**
     * What the hidden inputs submit, one entry per date and at least one: an empty date picker
     * still submits its name.
     *
     * @return list<string>
     */
    public function getHiddenInputValues(): array
    {
        /** @var list<string>|null $dates */
        $dates = $this->getDefaultValue();

        return $dates ?? [''];
    }

    #[ExposeToClient]
    /**
     * Only the plain-string translations: Zag builds the others (day cells, previous/next, ...) as
     * functions of the date and view, which can't be sent to the client.
     *
     * @return array{clearTrigger: string, monthSelect: string, yearSelect: string, content: string, weekColumnHeader: string}
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'clearTrigger' => 'datePicker.clearTrigger',
            'monthSelect' => 'datePicker.monthSelect',
            'yearSelect' => 'datePicker.yearSelect',
            'content' => 'datePicker.content',
            'weekColumnHeader' => 'datePicker.weekColumnHeader',
        ]);
    }
}
