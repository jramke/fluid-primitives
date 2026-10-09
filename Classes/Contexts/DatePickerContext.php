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

// Every public method is a getter a template or the client reads by name, so there is nothing to split off.
// @mago-expect lint:too-many-methods
#[Autoconfigure(public: true)]
class DatePickerContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    /**
     * The ids of the labels in the language file, which are the keys the client builds its
     * translations from.
     */
    private const array TRANSLATIONS = [
        'clearTrigger',
        'monthSelect',
        'yearSelect',
        'content',
        'weekColumnHeader',
        'triggerOpen',
        'triggerClose',
        'prevTriggerDay',
        'prevTriggerMonth',
        'prevTriggerYear',
        'nextTriggerDay',
        'nextTriggerMonth',
        'nextTriggerYear',
        'viewTriggerToDay',
        'viewTriggerToMonth',
        'viewTriggerToYear',
        'viewTriggerDay',
        'viewTriggerMonth',
        'viewTriggerYear',
        'presetTrigger',
        'weekNumberCell',
        'dayCell',
        'dayCellSelected',
        'dayCellRangeStart',
        'dayCellRangeEnd',
        'dayCellInRange',
        'dayCellUnavailable',
        'contentRoleDescription',
        'tableRoleDescriptionDay',
        'tableRoleDescriptionMonth',
        'tableRoleDescriptionYear',
        'placeholderDay',
        'placeholderMonth',
        'placeholderYear',
    ];

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
        // A Field sets `defaultValue` on the context without Fluid checking its type, so it can be any
        // shape handled below.
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

    // `min`, `max` and `defaultFocusedValue` take an ISO date string or a `DateTimeInterface`, but the
    // client only gets the ISO date.
    #[ExposeToClient(excludeIfNull: true)]
    public function getMin(): ?string
    {
        return DateUtility::toIsoDate($this->get('min'));
    }

    #[ExposeToClient(excludeIfNull: true)]
    public function getMax(): ?string
    {
        return DateUtility::toIsoDate($this->get('max'));
    }

    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultFocusedValue(): ?string
    {
        return DateUtility::toIsoDate($this->get('defaultFocusedValue'));
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
     * Every label as a text, with `%placeholders%` for what only the client knows (the date of a day
     * cell, ...): Zag builds most of its labels as functions of the date or the view, which can't
     * be sent to the client as they are.
     *
     * @return array{
     *     clearTrigger: string,
     *     monthSelect: string,
     *     yearSelect: string,
     *     content: string,
     *     weekColumnHeader: string,
     *     triggerOpen: string,
     *     triggerClose: string,
     *     prevTriggerDay: string,
     *     prevTriggerMonth: string,
     *     prevTriggerYear: string,
     *     nextTriggerDay: string,
     *     nextTriggerMonth: string,
     *     nextTriggerYear: string,
     *     viewTriggerToDay: string,
     *     viewTriggerToMonth: string,
     *     viewTriggerToYear: string,
     *     viewTriggerDay: string,
     *     viewTriggerMonth: string,
     *     viewTriggerYear: string,
     *     presetTrigger: string,
     *     weekNumberCell: string,
     *     dayCell: string,
     *     dayCellSelected: string,
     *     dayCellRangeStart: string,
     *     dayCellRangeEnd: string,
     *     dayCellInRange: string,
     *     dayCellUnavailable: string,
     *     contentRoleDescription: string,
     *     tableRoleDescriptionDay: string,
     *     tableRoleDescriptionMonth: string,
     *     tableRoleDescriptionYear: string,
     *     placeholderDay: string,
     *     placeholderMonth: string,
     *     placeholderYear: string,
     * }
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults(array_combine(self::TRANSLATIONS, array_map(
            static fn(string $key): string => 'datePicker.' . $key,
            self::TRANSLATIONS,
        )));
    }
}
