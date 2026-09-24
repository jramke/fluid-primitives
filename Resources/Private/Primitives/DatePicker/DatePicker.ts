import * as datePicker from '@zag-js/date-picker';
import { FieldAwareComponent, Machine, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

// PHP normalizes `defaultValue` to an array (or omits it) before it reaches the client - see
// DatePickerContext::getDefaultValue(), mirroring SelectContext. `min`/`max`/`defaultFocusedValue`
// pass through Root's plain `ui:prop`s as raw ISO strings instead.
const parseDateValue = (value?: unknown) => (value ? datePicker.parse(value as string) : undefined);
const parseDateValues = (values?: unknown) =>
    values ? datePicker.parse(values as string[]) : undefined;

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

    transformProps(props: datePicker.Props): datePicker.Props {
        return {
            ...props,
            defaultValue: parseDateValues(props.defaultValue),
            min: parseDateValue(props.min),
            max: parseDateValue(props.max),
            defaultFocusedValue: parseDateValue(props.defaultFocusedValue),
        };
    }

    initMachine(props: datePicker.Props): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(datePicker.machine, this.transformProps(props));
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
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        (['day', 'month', 'year'] as const).forEach(view => {
            this.spreadPropsByValue('view', ({ value }) =>
                value === view ? this.api.getViewProps({ view }) : null
            );
            this.spreadPropsByValue('viewControl', ({ value }) =>
                value === view ? this.api.getViewControlProps({ view }) : null
            );
            this.spreadPropsByValue('viewTrigger', ({ el, value }) => {
                if (value !== view) return null;
                el.textContent = this.api.visibleRangeText.formatted;
                return this.api.getViewTriggerProps({ view });
            });
            this.spreadPropsByValue('prevTrigger', ({ value }) =>
                value === view ? this.api.getPrevTriggerProps({ view }) : null
            );
            this.spreadPropsByValue('nextTrigger', ({ value }) =>
                value === view ? this.api.getNextTriggerProps({ view }) : null
            );
            this.spreadPropsByValue('table', ({ value }) =>
                value === view ? this.api.getTableProps({ view }) : null
            );

            this.buildTable(view);
        });

        this.spreadPropsByValue('presetTrigger', ({ value }) =>
            this.api.getPresetTriggerProps({ value: value as datePicker.DateRangePreset })
        );

        const monthSelectEl = this.getElement<HTMLSelectElement>('monthSelect');
        if (monthSelectEl) this.buildMonthSelect(monthSelectEl);

        const yearSelectEl = this.getElement<HTMLSelectElement>('yearSelect');
        if (yearSelectEl) this.buildYearSelect(yearSelectEl);
    }

    private buildTable(view: 'day' | 'month' | 'year') {
        const theadEl = this.getElements('tableHeader').find(el => el.dataset.value === view);
        const tbodyEl = this.getElements('tableBody').find(el => el.dataset.value === view);
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
