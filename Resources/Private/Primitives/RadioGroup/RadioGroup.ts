import * as radioGroup from '@zag-js/radio-group';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

export class RadioGroup extends FieldAwareComponent<radioGroup.Props, radioGroup.Api> {
    static name = 'radio-group';

    propsWithField(props: radioGroup.Props, fieldMachine: FieldMachine): radioGroup.Props {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: radioGroup.Props): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(radioGroup.machine, props);
    }

    initApi() {
        return radioGroup.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl)
            this.spreadProps(
                rootEl,
                mergeProps(this.api.getRootProps(), {
                    'aria-invalid': this.fieldMachine?.context.get('invalid') || undefined,
                    'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
                })
            );

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        // Note: previously read `getAttribute('data-disabled'/'data-invalid') === 'true'`, which
        // could never match - TagAttributes only ever renders these as bare boolean attributes
        // (present/absent), never as the literal string "true". `hasAttribute` is the fix.
        this.spreadPropsByValue('item', ({ el, value }) =>
            this.api.getItemProps({
                value,
                disabled: el.hasAttribute('data-disabled'),
                invalid: el.hasAttribute('data-invalid'),
            })
        );

        this.spreadPropsByValue('itemText', ({ el, value }) =>
            this.api.getItemTextProps({
                value,
                disabled: el.hasAttribute('data-disabled'),
                invalid: el.hasAttribute('data-invalid'),
            })
        );

        this.spreadPropsByValue('itemControl', ({ el, value }) =>
            this.api.getItemControlProps({
                value,
                disabled: el.hasAttribute('data-disabled'),
                invalid: el.hasAttribute('data-invalid'),
            })
        );

        this.spreadPropsByValue('itemHiddenInput', ({ el, value }) =>
            this.api.getItemHiddenInputProps({
                value,
                disabled: el.hasAttribute('data-disabled'),
                invalid: el.hasAttribute('data-invalid'),
            })
        );

        const indicatorEl = this.getElement('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
