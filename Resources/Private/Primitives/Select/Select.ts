import * as select from '@zag-js/select';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { registerClientPropConverters } from '../../Client/src/lib/client-prop-converters';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { ListCollectionData } from '../../Client/src/types.generated';
import type { FieldMachine } from '../Field/src/field.registry';
export type { SelectHydrationProps } from './Select.hydration';

// Runs once, at import time, before any mountAll()/mount() call - same ordering guarantee this
// codebase already relies on for e.g. static componentName. Converts the wire-shape
// `ListCollectionData` into a real `ListCollection` before the constructor ever sees it, so
// `new Select(props)` needs no cast between the generated hydration props and this class's own
// `select.Props`. Wrapped rather than passed directly - `getListCollectionFromHydrationData` is
// itself generic, and TS can't infer its type parameter through registerClientPropConverters's own
// generic `convert` parameter; annotating `collection` here gives it a concrete type to infer from.
registerClientPropConverters('select', {
    collection: (collection: ListCollectionData) => getListCollectionFromHydrationData(collection),
});

declare module 'fluid-primitives/client' {
    interface HydrationPropsOverrides {
        select: { collection: select.Props['collection'] };
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
        props = this.withFieldProps(props);
        return new Machine(select.machine, props);
    }

    initApi() {
        return select.connect(this.machine.service, normalizeProps);
    }

    render = () => {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const hiddenSelectEl = this.getElement('hiddenSelect');
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

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const triggerEl = this.getElement('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        // We need to make sure the element is rerendered because otherwise safari doesnt update the spans value in the a11y tree
        // and the button would announce an old value when it receives focus.
        // see: https://github.com/chakra-ui/zag/issues/3099
        const valueTextEl = this.getElement('valueText');
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
                    const el = this.getElement('valueText');
                    if (el?.isConnected) {
                        const next = el.cloneNode(true) as HTMLElement;
                        el.replaceWith(next);
                        next.replaceWith(el);
                    }
                });
            }
        }

        this.spreadPropsByValue(
            'itemGroup',
            ({ value }) => {
                return this.api.getItemGroupProps({ id: value });
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemGroupLabel',
            ({ value }) => {
                return this.api.getItemGroupLabelProps({ htmlFor: value });
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'item',
            ({ value }) => {
                const item = this.api.collection.find(value);
                return item ? this.api.getItemProps({ item }) : null;
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemText',
            ({ value }) => {
                const item = this.api.collection.find(value);
                return item ? this.api.getItemTextProps({ item }) : null;
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemIndicator',
            ({ value }) => {
                const item = this.api.collection.find(value);
                return item ? this.api.getItemIndicatorProps({ item }) : null;
            },
            { parent: this.doc }
        );

        const clearTriggerEl = this.getElement('clearTrigger');
        if (clearTriggerEl) {
            const clearTriggerProps = mergeProps(this.api.getClearTriggerProps(), {
                'aria-label': this.userProps?.translations?.clearTriggerLabel || null,
            });
            this.spreadProps(clearTriggerEl, clearTriggerProps);
        }

        const indicatorEl = this.getElement('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    };
}
