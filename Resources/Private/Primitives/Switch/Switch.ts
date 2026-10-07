import * as zagSwitch from '@zag-js/switch';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';

export class Switch extends FieldAwareComponent<zagSwitch.Props, zagSwitch.Api> {
    static componentName = 'switch';

    propsWithField(props: zagSwitch.Props, field: FieldClientApi): zagSwitch.Props {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: zagSwitch.Props): Machine<any> {
        props = this.withFieldProps(props);
        const [machineProps] = zagSwitch.splitProps(props);
        return new Machine(zagSwitch.machine, machineProps);
    }

    initApi() {
        return zagSwitch.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const thumbEl = this.hydrator.query('thumb');
        if (thumbEl) this.spreadProps(thumbEl, this.api.getThumbProps());

        const hiddenInputEl = this.hydrator.query('hiddenInput');
        if (hiddenInputEl) {
            const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
                'aria-describedby': this.field?.ariaDescribedby,
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
