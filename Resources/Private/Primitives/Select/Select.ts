import * as select from '@zag-js/select';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { FieldMachine } from '../Field/src/field.registry';

export class Select extends FieldAwareComponent<select.Props, select.Api> {
    static name = 'select';

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

    // TODO: we need to make sure that selecting a value does correctly dispatch a change/input event beause the form relies on it to update the formdata.
    // currently form validation does not show the current error state on item change (see numberinput for example)
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

        // TODO: we need to handle the select state for the options manually since zag-js dont do it (maybe we can provide a pr),
        // the formData would choose the first option as the value for the select when no option is selected
        const hiddenSelectEl = this.getElement('hiddenSelect');
        if (hiddenSelectEl) {
            const mergedProps = mergeProps(this.api.getHiddenSelectProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(hiddenSelectEl, mergedProps);

            const options = Array.from(hiddenSelectEl.querySelectorAll('option'));
            const collection = this.api.collection;

            const isValueEmpty = this.api.value.length === 0;
            console.log({ options, collection, isValueEmpty });

            options[0].selected = isValueEmpty;
            options.shift();

            console.log({ options });

            // for (const option of options) {
            //     const item = collection.find(option.value);
            //     if (item) {
            //         const itemState = this.api.getItemState(item);
            //         option.disabled = itemState.disabled;
            //         option.selected = itemState.selected;
            //     }
            // }
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
