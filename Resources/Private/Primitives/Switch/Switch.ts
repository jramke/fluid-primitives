import * as zagSwitch from '@zag-js/switch';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

export class Switch extends FieldAwareComponent<zagSwitch.Props, zagSwitch.Api> {
    static componentName = 'switch';

    propsWithField(props: zagSwitch.Props, fieldMachine: FieldMachine): zagSwitch.Props {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: zagSwitch.Props): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(zagSwitch.machine, props);
    }

    initApi() {
        return zagSwitch.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const thumbEl = this.getElement('thumb');
        if (thumbEl) this.spreadProps(thumbEl, this.api.getThumbProps());

        const hiddenInputEl = this.getElement('hiddenInput');
        if (hiddenInputEl) {
            const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(hiddenInputEl, mergedProps);
        }

        this.spreadPropsByValue('indicator', ({ value }) => {
            const isActive = (value === 'checked') === this.api.checked;
            return normalizeProps.element({
                'aria-hidden': true,
                hidden: isActive ? undefined : true,
                'data-state': value,
            });
        });
    }
}
