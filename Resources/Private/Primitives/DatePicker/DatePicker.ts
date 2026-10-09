import { toZoned } from '@internationalized/date';
import * as datePicker from '@zag-js/date-picker';
import type { Attrs } from '@zag-js/vanilla';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';
import {
    buildTranslations,
    getPlaceholder,
    type DatePickerTranslations,
    type TranslationTexts,
} from './src/date-picker.translations';

const parseDate = (date?: string) => (date ? datePicker.parse(date) : undefined);

// Zag asks for the id of a label and of an input by index (range mode has two of each), but PHP can
// only send a single string, e.g. the id of the surrounding Field: it names the first one, the
// others derive from it.
const indexedId = (id: string) => (index: number) => (index === 0 ? id : `${id}:${index}`);

// Wire shape -> what the machine takes, see client-prop-converters.ts. The override type below is
// derived from this const via `typeof`, so the two can't drift.
const datePickerPropConverters = {
    // PHP sends ISO date strings (`defaultValue` always as a list), the machine wants DateValues.
    min: parseDate,
    max: parseDate,
    defaultFocusedValue: parseDate,
    defaultValue: (dates?: string[]) => (dates ? datePicker.parse(dates) : undefined),
    // A calendar highlights "today" for the person looking at it, which Zag's own UTC default gets
    // wrong for part of the day.
    timeZone: (timeZone?: string) => timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
    ids: ({ label, input, ...ids }: Record<string, string> = {}) =>
        ({
            ...ids,
            ...(label && { label: indexedId(label) }),
            ...(input && { input: indexedId(input) }),
        }) as datePicker.ElementIds,
    // The labels are texts on the wire, Zag wants functions of the date and the view for most.
    translations: (texts: TranslationTexts | undefined, props: Record<string, unknown>) =>
        buildTranslations(texts, props.locale as string | undefined),
    // PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
    // prop (see WireTypeResolver), so this resolves to `unknown` on the wire - this just tells TS
    // what it actually is (a real @zag-js/popper PositioningOptions object).
    positioning: (positioning: datePicker.Props['positioning']) => positioning,
} satisfies ClientPropConverterMap;

registerClientPropConverters('datePicker', datePickerPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        datePicker: ConverterMachineProps<typeof datePickerPropConverters>;
    }
}

export class DatePicker extends FieldAwareComponent<datePicker.Props, datePicker.Api> {
    static componentName = 'datePicker';

    propsWithField(props: datePicker.Props, field: FieldClientApi): datePicker.Props {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    transformProps(props: datePicker.Props): datePicker.Props {
        return {
            ...props,
            placeholder:
                props.placeholder ??
                getPlaceholder(
                    props.translations as DatePickerTranslations | undefined,
                    props.locale
                ),
            // Zag writes a date picked in the calendar into the input without an event, but a Field
            // and a Form only learn of a change from one (and read the value once it has settled).
            onValueChange: details => {
                this.hydrator.query('input')?.dispatchEvent(new Event('change', { bubbles: true }));
                props.onValueChange?.(details);
            },
        };
    }

    initMachine(props: datePicker.Props): Machine<any> {
        const [machineProps] = datePicker.splitProps(this.withFieldProps(props));
        return new Machine(datePicker.machine, machineProps);
    }

    initApi() {
        return datePicker.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        this.spreadPropsByOptionalValue('label', ({ value }) =>
            this.api.getLabelProps({ index: Number(value ?? 0) })
        );

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        // The visible input holds the text as typed, the hidden inputs submit the dates.
        this.spreadPropsByOptionalValue('input', ({ value }) => {
            const { name, ...inputProps } = this.api.getInputProps({ index: Number(value ?? 0) });
            return mergeProps(inputProps, { 'aria-describedby': this.field?.ariaDescribedby });
        });
        this.renderHiddenInputs();

        const clearTriggerEl = this.hydrator.query('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const triggerEl = this.hydrator.query('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) {
            const positionerProps = this.api.getPositionerProps();
            // An `inline` calendar has no trigger to anchor to, so it never gets placed - and the
            // floating styles of an un-placed positioner push it a viewport-height off-screen.
            if (this.api.inline) delete positionerProps.style;
            this.spreadProps(positionerEl, positionerProps);
        }

        // Zag hard-codes English role descriptions for the content and the tables.
        const roleDescription = (this.userProps.translations as DatePickerTranslations | undefined)
            ?.roleDescription;

        const contentEl = this.hydrator.query('content');
        if (contentEl) {
            this.spreadProps(
                contentEl,
                mergeProps(this.api.getContentProps(), {
                    'aria-roledescription': roleDescription?.content,
                })
            );
        }

        this.spreadPropsByValue('view', ({ value }) =>
            this.api.getViewProps({ view: value as datePicker.DateView })
        );
        this.spreadPropsByValue('viewControl', ({ value }) =>
            this.api.getViewControlProps({ view: value as datePicker.DateView })
        );
        this.spreadPropsByValue('viewTrigger', ({ value }) =>
            this.api.getViewTriggerProps({ view: value as datePicker.DateView })
        );
        this.spreadPropsByValue('prevTrigger', ({ value }) =>
            this.api.getPrevTriggerProps({ view: value as datePicker.DateView })
        );
        this.spreadPropsByValue('nextTrigger', ({ value }) =>
            this.api.getNextTriggerProps({ view: value as datePicker.DateView })
        );
        this.spreadPropsByValue('presetTrigger', ({ value }) =>
            this.api.getPresetTriggerProps({ value: value as datePicker.DateRangePreset })
        );

        // Zag only joins the first and last visible month in range mode, and then also when it is one.
        const { start, end } = this.api.visibleRangeText;
        const rangeText = start === end ? start : `${start} - ${end}`;
        this.hydrator.queryAll('rangeText').forEach(rangeTextEl => {
            this.spreadProps(rangeTextEl, this.api.getRangeTextProps());
            if (rangeTextEl.textContent !== rangeText) rangeTextEl.textContent = rangeText;
        });

        this.hydrator.queryAll('table').forEach(tableEl => {
            const view = tableEl.dataset.value as datePicker.DateView;
            const offset = Number(tableEl.dataset.offset ?? 0);

            // The months of a day view look alike: each gets its own id and is named after its month.
            this.spreadProps(
                tableEl,
                mergeProps(
                    this.api.getTableProps({ view, id: offset ? String(offset) : undefined }),
                    {
                        'aria-roledescription': roleDescription?.table?.[view],
                        ...(view === 'day' && {
                            'aria-label': this.api.getOffset({ months: offset }).visibleRangeText
                                .start,
                        }),
                    }
                )
            );

            const headerEl = this.hydrator.query('tableHeader', tableEl);
            const bodyEl = this.hydrator.query('tableBody', tableEl);
            if (headerEl && bodyEl) this.renderTable(view, offset, headerEl, bodyEl);
        });

        const monthSelectEl = this.hydrator.query<HTMLSelectElement>('monthSelect');
        if (monthSelectEl) {
            this.renderSelect(
                monthSelectEl,
                this.api.getMonthSelectProps(),
                this.api.getMonths(),
                this.api.visibleRange.start.month
            );
        }

        const yearSelectEl = this.hydrator.query<HTMLSelectElement>('yearSelect');
        if (yearSelectEl) {
            this.renderSelect(
                yearSelectEl,
                this.api.getYearSelectProps(),
                this.api.getYears(),
                this.api.visibleRange.start.year
            );
        }
    }

    // One hidden input per selected date, and one empty one when there is none: the name is always
    // submitted. A date goes as the W3C date-time of its midnight in the server's time zone, the
    // format Extbase maps to a DateTime as it is, see DatePickerContext::getHiddenInputValues().
    private renderHiddenInputs() {
        const { serverTimeZone } = this.userProps as datePicker.Props & { serverTimeZone: string };
        const values =
            this.api.value.length > 0
                ? this.api.value.map(date =>
                      toZoned(date, serverTimeZone)
                          .toString()
                          .replace(/\[.*\]$/, '')
                  )
                : [''];
        const inputEls = this.hydrator.queryAll<HTMLInputElement>('hiddenInput');
        inputEls.slice(values.length).forEach(inputEl => inputEl.remove());

        values.forEach((value, i) => {
            let inputEl = inputEls[i];
            if (!inputEl) {
                inputEl = this.doc.createElement('input');
                this.hydrator.stamp(inputEl, 'hiddenInput');
                if (i > 0) inputEls[i - 1].after(inputEl);
                else this.hydrator.query('root')?.append(inputEl);
                inputEls[i] = inputEl;
            }
            this.spreadProps(
                inputEl,
                normalizeProps.input({
                    type: 'hidden',
                    name: this.machine.prop('name'),
                    value,
                    disabled: this.api.disabled,
                })
            );
        });
    }

    // The grid is not part of the server markup: its size depends on the locale, the calendar and
    // the month shown. It is built from what is there instead of from scratch on every render, so
    // the focused cell and the hover state survive the machine's own updates.
    private renderTable(
        view: datePicker.DateView,
        offset: number,
        headerEl: Element,
        bodyEl: Element
    ) {
        if (view === 'day') {
            this.renderDayTable(offset, headerEl, bodyEl);
            return;
        }

        const grid =
            view === 'month'
                ? this.api.getMonthsGrid({ columns: 4, format: 'short' })
                : this.api.getYearsGrid({ columns: 4 });

        headerEl.replaceChildren();
        this.renderRows(bodyEl, grid, rowEl => {
            this.spreadProps(rowEl, this.api.getTableRowProps({ view }));
        }).forEach(([rowEl, cells]) => {
            this.fitChildren<HTMLTableCellElement>(rowEl, 'td', cells.length).forEach(
                (cellEl, i) => {
                    const cell = cells[i];
                    this.renderCell(
                        cellEl,
                        view === 'month'
                            ? this.api.getMonthTableCellProps(cell)
                            : this.api.getYearTableCellProps(cell),
                        view === 'month'
                            ? this.api.getMonthTableCellTriggerProps(cell)
                            : this.api.getYearTableCellTriggerProps(cell),
                        cell.label
                    );
                }
            );
        });
    }

    private renderDayTable(offset: number, headerEl: Element, bodyEl: Element) {
        const view = 'day';
        const weekNumbers = this.api.showWeekNumbers;
        // A cell is outside the range of its own month, not of the whole visible range.
        const { weeks, visibleRange } = this.api.getOffset({ months: offset });

        const [headerRowEl] = this.fitChildren<HTMLTableRowElement>(headerEl, 'tr', 1);
        this.spreadProps(headerRowEl, this.api.getTableRowProps({ view }));

        const headEls = this.fitChildren<HTMLTableCellElement>(
            headerRowEl,
            'th',
            this.api.weekDays.length + (weekNumbers ? 1 : 0)
        );
        if (weekNumbers) {
            const weekNumberHeaderEl = headEls.shift()!;
            this.spreadProps(weekNumberHeaderEl, this.api.getWeekNumberHeaderCellProps({ view }));
            weekNumberHeaderEl.textContent = '';
        }
        this.api.weekDays.forEach((day, i) => {
            this.spreadProps(
                headEls[i],
                mergeProps(this.api.getTableHeadProps({ view }), {
                    scope: 'col',
                    'aria-label': day.long,
                })
            );
            if (headEls[i].textContent !== day.narrow) headEls[i].textContent = day.narrow;
        });

        this.renderRows(bodyEl, weeks, rowEl => {
            this.spreadProps(rowEl, this.api.getTableRowProps({ view }));
        }).forEach(([rowEl, week], weekIndex) => {
            const cellEls = this.fitChildren<HTMLTableCellElement>(
                rowEl,
                'td',
                week.length + (weekNumbers ? 1 : 0)
            );
            if (weekNumbers) {
                const weekNumberEl = cellEls.shift()!;
                this.spreadProps(
                    weekNumberEl,
                    this.api.getWeekNumberCellProps({ weekIndex, week })
                );
                weekNumberEl.textContent = String(this.api.getWeekNumber(week));
            }
            week.forEach((value, i) => {
                this.renderCell(
                    cellEls[i],
                    this.api.getDayTableCellProps({ value, visibleRange }),
                    this.api.getDayTableCellTriggerProps({ value, visibleRange }),
                    String(value.day)
                );
            });
        });
    }

    /** One `<tr>` per entry of `rows`, paired with the entry it renders. */
    private renderRows<T>(
        parentEl: Element,
        rows: T[],
        spreadRow: (rowEl: HTMLTableRowElement) => void
    ): [HTMLTableRowElement, T][] {
        return this.fitChildren<HTMLTableRowElement>(parentEl, 'tr', rows.length).map(
            (rowEl, i) => {
                spreadRow(rowEl);
                return [rowEl, rows[i]];
            }
        );
    }

    private renderCell(cellEl: HTMLElement, cellProps: Attrs, triggerProps: Attrs, text: string) {
        this.spreadProps(cellEl, cellProps);
        const [triggerEl] = this.fitChildren<HTMLDivElement>(cellEl, 'div', 1);
        this.spreadProps(triggerEl, triggerProps);
        if (triggerEl.textContent !== text) triggerEl.textContent = text;
    }

    private renderSelect(
        selectEl: HTMLSelectElement,
        selectProps: Attrs,
        options: datePicker.Cell[],
        selected: number
    ) {
        this.spreadProps(selectEl, selectProps);
        this.fitChildren<HTMLOptionElement>(selectEl, 'option', options.length).forEach(
            (optionEl, i) => {
                const { label, value, disabled } = options[i];
                if (optionEl.value !== String(value)) optionEl.value = String(value);
                if (optionEl.textContent !== label) optionEl.textContent = label;
                if (optionEl.disabled !== !!disabled) optionEl.disabled = !!disabled;
            }
        );
        // The options may be new, which resets the selection without Zag's `value` prop changing.
        selectEl.value = String(selected);
    }

    /**
     * Makes `parentEl` hold exactly `count` children of `tag`: it keeps the ones already there,
     * adds the missing ones and removes the extra ones. A child of another tag (the placeholder
     * rows of a skeleton) is replaced.
     */
    private fitChildren<T extends HTMLElement>(parentEl: Element, tag: string, count: number): T[] {
        const children = Array.from(parentEl.children);
        children.slice(count).forEach(child => child.remove());

        return Array.from({ length: count }, (_, i) => {
            const child = children[i];
            if (child?.localName === tag) return child as T;

            const el = this.doc.createElement(tag) as T;
            if (child) child.replaceWith(el);
            else parentEl.append(el);
            return el;
        });
    }
}
