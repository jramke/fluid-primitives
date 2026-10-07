import * as radioGroup from '@zag-js/radio-group';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';

export class RadioGroup extends FieldAwareComponent<radioGroup.Props, radioGroup.Api> {
    static componentName = 'radioGroup';

    propsWithField(props: radioGroup.Props, field: FieldClientApi): radioGroup.Props {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: radioGroup.Props): Machine<any> {
        props = this.withFieldProps(props);
        const [machineProps] = radioGroup.splitProps(props);
        return new Machine(radioGroup.machine, machineProps);
    }

    initApi() {
        return radioGroup.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl)
            this.spreadProps(
                rootEl,
                mergeProps(this.api.getRootProps(), {
                    'aria-invalid': this.field?.invalid || undefined,
                    'aria-describedby': this.field?.ariaDescribedby,
                })
            );

        const labelEl = this.hydrator.query('label');
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

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
