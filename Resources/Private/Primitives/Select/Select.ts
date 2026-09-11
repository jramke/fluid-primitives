import * as select from '@zag-js/select';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { FieldMachine } from '../Field/src/field.registry';

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

    transformProps(props: select.Props): select.Props {
        return {
            ...props,
            get collection() {
                return getListCollectionFromHydrationData(props.collection);
            },
        };
    }

    initMachine(props: select.Props): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(select.machine, this.transformProps(props));
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

        const itemGroupEls = this.getElements('itemGroup');
        itemGroupEls.forEach(itemGroupEl => {
            this.spreadProps(
                itemGroupEl,
                this.api.getItemGroupProps({ id: itemGroupEl.dataset.id! })
            );
            const itemGroupLabelEl = this.getElement('itemGroupLabel', itemGroupEl);
            if (itemGroupLabelEl) {
                this.spreadProps(
                    itemGroupLabelEl,
                    this.api.getItemGroupLabelProps({ htmlFor: itemGroupEl.dataset.id! })
                );
            }
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
