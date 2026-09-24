import * as datePicker from '@zag-js/date-picker';
import {
    FieldAwareComponent,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

// PHP normalizes `defaultValue` to an array (or omits it) before it reaches the client - see
// DatePickerContext::getDefaultValue(), mirroring SelectContext. `min`/`max`/`defaultFocusedValue`
// pass through Root's plain `ui:prop`s as raw ISO strings instead. Registered as converters (not in
// transformProps) so mountAll/mount convert them before the component is even constructed - see
// Select.ts's own `collection` converter for the same reasoning.
const parseDateValue = (value?: unknown) => (value ? datePicker.parse(value as string) : undefined);
const parseDateValues = (values?: unknown) =>
    values ? datePicker.parse(values as string[]) : undefined;

const datePickerPropConverters = {
    min: parseDateValue,
    max: parseDateValue,
    defaultFocusedValue: parseDateValue,
    defaultValue: parseDateValues,
    // DatePickerContext::getTranslations() returns a plain array - the generated wire type has no
    // way to know it matches @zag-js/date-picker's own IntlTranslations shape, so this just tells
    // TS what it actually is rather than transforming the value itself (same as positioning below).
    translations: (translations: datePicker.Props['translations']) => translations,
    // PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
    // prop (see WireTypeResolver), so `positioning` resolves to `unknown` on the wire - this just
    // tells TS what it actually is (a real @zag-js/popper PositioningOptions object).
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

    propsWithField(props: datePicker.Props, fieldMachine: FieldMachine): datePicker.Props {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: datePicker.Props): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(datePicker.machine, props);
    }

    initApi() {
        return datePicker.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        this.spreadPropsByOptionalValue('label', ({ value }) =>
            this.api.getLabelProps({ index: Number(value ?? 0) })
        );

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        this.spreadPropsByOptionalValue('input', ({ value }) =>
            this.api.getInputProps({ index: Number(value ?? 0) })
        );

        const clearTriggerEl = this.getElement('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const triggerEl = this.getElement('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const rangeTextEl = this.getElement('rangeText');
        if (rangeTextEl) this.spreadProps(rangeTextEl, this.api.getRangeTextProps());

        const positionerEl = this.getElement('positioner');
        if (positionerEl) {
            const positionerProps = this.api.getPositionerProps();
            // `getPositionerProps()` always returns @zag-js/popper's floating styles, even for
            // `inline` - with no trigger to anchor to, no placement ever gets computed, and an
            // un-placed floating element gets `transform: translate3d(0, -100vh, 0)` from
            // @zag-js/popper's own default (shoved a full viewport-height off-screen). `inline`
            // wants normal document flow instead, so drop the style and let it sit static.
            if (this.api.inline) delete positionerProps.style;
            this.spreadProps(positionerEl, positionerProps);
        }

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        // Every part below lives inside `content`, which `ui:portal` may have moved out from under
        // `root` entirely (the default for a popup - only `inline` leaves it in place) - `getElements`
        // defaults to searching within `root` when no `parent` is given, so without this, none of
        // these would ever be found once portaled, and `buildTable()` would always bail out early on
        // a missing `theadEl`/`tbodyEl`. Mirrors Select.ts/Combobox.ts's own item/itemGroup lookups,
        // which need the same `{ parent: this.doc }` for the same reason.
        (['day', 'month', 'year'] as const).forEach(view => {
            this.spreadPropsByValue(
                'view',
                ({ value }) => (value === view ? this.api.getViewProps({ view }) : null),
                { parent: this.doc }
            );
            this.spreadPropsByValue(
                'viewControl',
                ({ value }) => (value === view ? this.api.getViewControlProps({ view }) : null),
                { parent: this.doc }
            );
            this.spreadPropsByValue(
                'viewTrigger',
                ({ el, value }) => {
                    if (value !== view) return null;
                    el.textContent = this.api.visibleRangeText.formatted;
                    return this.api.getViewTriggerProps({ view });
                },
                { parent: this.doc }
            );
            this.spreadPropsByValue(
                'prevTrigger',
                ({ value }) => (value === view ? this.api.getPrevTriggerProps({ view }) : null),
                { parent: this.doc }
            );
            this.spreadPropsByValue(
                'nextTrigger',
                ({ value }) => (value === view ? this.api.getNextTriggerProps({ view }) : null),
                { parent: this.doc }
            );
            this.spreadPropsByValue(
                'table',
                ({ value }) => (value === view ? this.api.getTableProps({ view }) : null),
                { parent: this.doc }
            );

            this.buildTable(view);
        });

        this.spreadPropsByValue(
            'presetTrigger',
            ({ value }) =>
                this.api.getPresetTriggerProps({ value: value as datePicker.DateRangePreset }),
            { parent: this.doc }
        );

        const monthSelectEl = this.getElement<HTMLSelectElement>('monthSelect');
        if (monthSelectEl) this.buildMonthSelect(monthSelectEl);

        const yearSelectEl = this.getElement<HTMLSelectElement>('yearSelect');
        if (yearSelectEl) this.buildYearSelect(yearSelectEl);
    }

    private buildTable(view: 'day' | 'month' | 'year') {
        const theadEl = this.getElements('tableHeader', this.doc).find(
            el => el.dataset.value === view
        );
        const tbodyEl = this.getElements('tableBody', this.doc).find(
            el => el.dataset.value === view
        );
        if (!theadEl || !tbodyEl) return;

        theadEl.replaceChildren();
        tbodyEl.replaceChildren();

        if (view === 'day') {
            this.buildDayTable(theadEl, tbodyEl, view);
            return;
        }

        const grid =
            view === 'month'
                ? this.api.getMonthsGrid({ columns: 4, format: 'short' })
                : this.api.getYearsGrid({ columns: 4 });

        grid.forEach(row => {
            const trEl = this.doc.createElement('tr');
            row.forEach(cell => {
                const tdEl = this.doc.createElement('td');
                const props =
                    view === 'month'
                        ? this.api.getMonthTableCellProps(cell)
                        : this.api.getYearTableCellProps(cell);
                this.spreadProps(tdEl, props);

                const triggerEl = this.doc.createElement('div');
                const triggerProps =
                    view === 'month'
                        ? this.api.getMonthTableCellTriggerProps(cell)
                        : this.api.getYearTableCellTriggerProps(cell);
                this.spreadProps(triggerEl, triggerProps);
                triggerEl.textContent = cell.label;
                tdEl.appendChild(triggerEl);
                trEl.appendChild(tdEl);
            });
            tbodyEl.appendChild(trEl);
        });
    }

    private buildDayTable(theadEl: Element, tbodyEl: Element, view: 'day') {
        const headerRowEl = this.doc.createElement('tr');
        this.spreadProps(headerRowEl, this.api.getTableRowProps({ view }));

        if (this.api.showWeekNumbers) {
            const thEl = this.doc.createElement('th');
            this.spreadProps(thEl, this.api.getWeekNumberHeaderCellProps({ view }));
            headerRowEl.appendChild(thEl);
        }

        this.api.weekDays.forEach(day => {
            const thEl = this.doc.createElement('th');
            thEl.scope = 'col';
            thEl.ariaLabel = day.long;
            this.spreadProps(thEl, this.api.getTableHeadProps({ view }));
            thEl.textContent = day.narrow;
            headerRowEl.appendChild(thEl);
        });
        theadEl.appendChild(headerRowEl);

        this.api.weeks.forEach((week, weekIndex) => {
            const trEl = this.doc.createElement('tr');
            this.spreadProps(trEl, this.api.getTableRowProps({ view }));

            if (this.api.showWeekNumbers) {
                const tdEl = this.doc.createElement('td');
                this.spreadProps(tdEl, this.api.getWeekNumberCellProps({ weekIndex, week }));
                tdEl.textContent = String(this.api.getWeekNumber(week));
                trEl.appendChild(tdEl);
            }

            week.forEach(value => {
                const tdEl = this.doc.createElement('td');
                this.spreadProps(tdEl, this.api.getDayTableCellProps({ value }));

                const triggerEl = this.doc.createElement('div');
                this.spreadProps(triggerEl, this.api.getDayTableCellTriggerProps({ value }));
                triggerEl.textContent = String(value.day);
                tdEl.appendChild(triggerEl);
                trEl.appendChild(tdEl);
            });
            tbodyEl.appendChild(trEl);
        });
    }

    private buildMonthSelect(selectEl: HTMLSelectElement) {
        this.spreadProps(selectEl, this.api.getMonthSelectProps());
        selectEl.replaceChildren(
            ...this.api.getMonths().map(month => {
                const optionEl = this.doc.createElement('option');
                optionEl.value = String(month.value);
                optionEl.textContent = month.label;
                return optionEl;
            })
        );
    }

    private buildYearSelect(selectEl: HTMLSelectElement) {
        this.spreadProps(selectEl, this.api.getYearSelectProps());
        selectEl.replaceChildren(
            ...this.api.getYears().map(year => {
                const optionEl = this.doc.createElement('option');
                optionEl.value = String(year.value);
                optionEl.textContent = year.label;
                return optionEl;
            })
        );
    }
}
