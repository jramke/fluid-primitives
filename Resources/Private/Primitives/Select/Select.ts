import * as select from '@zag-js/select';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { FieldMachine } from '../Field/src/field.registry';

// Wire shape -> real @zag-js/collection ListCollection instance. Registered here (not in
// transformProps) so mountAll/mount convert it before the component is even constructed - see
// client-prop-converters.ts. The override type below is derived from this const via `typeof`
// rather than hand-typed a second time, so the two can't drift.
const selectPropConverters = {
    collection: (collection: Parameters<typeof getListCollectionFromHydrationData>[0]) =>
        getListCollectionFromHydrationData(collection),
    // PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
    // prop (see WireTypeResolver), so `positioning` resolves to `unknown` on the wire - this just
    // tells TS what it actually is (a real @zag-js/popper PositioningOptions object).
    positioning: (positioning: select.Props['positioning']) => positioning,
} satisfies ClientPropConverterMap;

registerClientPropConverters('select', selectPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        select: ConverterMachineProps<typeof selectPropConverters>;
    }
}

export class Select extends FieldAwareComponent<select.Props, select.Api> {
    static componentName = 'select';

    propsWithField(props: select.Props, fieldMachine: FieldMachine): select.Props {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: select.Props): Machine<any> {
        const [machineProps] = select.splitProps(this.withFieldProps(props));
        return new Machine(select.machine, machineProps);
    }

    initApi() {
        return select.connect(this.machine.service, normalizeProps);
    }

    render = () => {
        this.subscribeToFieldService();

        const rootEl = this.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const controlEl = this.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const hiddenSelectEl = this.query('hiddenSelect');
        if (hiddenSelectEl) {
            const mergedProps = mergeProps(this.api.getHiddenSelectProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(hiddenSelectEl, mergedProps);

            // We need to handle this client side so the select can default to an empty string
            // Setting the select attribute server side has no effect
            // There is no need to mirror the selected property on the other options since the select inputs value is correctly updated by the machine
            const isValueEmpty = this.api.value.length === 0;
            const defaultOption = hiddenSelectEl.querySelector('option');
            if (defaultOption) defaultOption.selected = isValueEmpty;
        }

        const labelEl = this.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const triggerEl = this.query('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const positionerEl = this.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const listEl = this.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        // We need to make sure the element is rerendered because otherwise safari doesnt update the spans value in the a11y tree
        // and the button would announce an old value when it receives focus.
        // see: https://github.com/chakra-ui/zag/issues/3099
        const valueTextEl = this.query('valueText');
        if (valueTextEl) {
            const currentText = valueTextEl.textContent || valueTextEl.dataset.placeholder || '';
            const nextValue = this.api.valueAsString || valueTextEl.dataset.placeholder || '';

            this.spreadProps(
                valueTextEl,
                mergeProps(this.api.getValueTextProps(), {
                    children: nextValue,
                })
            );

            if (nextValue !== currentText) {
                queueMicrotask(() => {
                    const el = this.query('valueText');
                    if (el?.isConnected) {
                        const next = el.cloneNode(true) as HTMLElement;
                        el.replaceWith(next);
                        next.replaceWith(el);
                    }
                });
            }
        }

        this.spreadPropsByValue('itemGroup', ({ value }) => {
            return this.api.getItemGroupProps({ id: value });
        });

        this.spreadPropsByValue('itemGroupLabel', ({ value }) => {
            return this.api.getItemGroupLabelProps({ htmlFor: value });
        });

        this.spreadPropsByValue('item', ({ value }) => {
            const item = this.api.collection.find(value);
            return item ? this.api.getItemProps({ item }) : null;
        });

        this.spreadPropsByValue('itemText', ({ value }) => {
            const item = this.api.collection.find(value);
            return item ? this.api.getItemTextProps({ item }) : null;
        });

        this.spreadPropsByValue('itemIndicator', ({ value }) => {
            const item = this.api.collection.find(value);
            return item ? this.api.getItemIndicatorProps({ item }) : null;
        });

        const clearTriggerEl = this.query('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const indicatorEl = this.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    };
}
