import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';
import { connect } from './src/checkbox-group.connect';
import { machine } from './src/checkbox-group.machine';
import { splitProps } from './src/checkbox-group.props';
import { registerCheckboxGroup, unregisterCheckboxGroup } from './src/checkbox-group.registry';
import type { CheckboxGroupApi, CheckboxGroupProps } from './src/checkbox-group.types';

export class CheckboxGroup extends FieldAwareComponent<CheckboxGroupProps, CheckboxGroupApi> {
    static componentName = 'checkboxGroup';

    propsWithField(props: CheckboxGroupProps, field: FieldClientApi): CheckboxGroupProps {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: CheckboxGroupProps) {
        props = this.withFieldProps(props);
        const [machineProps] = splitProps(props);
        const createdMachine = new Machine(machine, machineProps);
        registerCheckboxGroup(this.hydrator.query('root'), createdMachine);
        return createdMachine;
    }

    initApi() {
        return connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) {
            const mergedProps = mergeProps(this.api.getRootProps(), {
                'aria-describedby': this.field?.ariaDescribedby,
            });
            this.spreadProps(rootEl, mergedProps);
        }

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());
    }

    destroy() {
        unregisterCheckboxGroup(this.hydrator.query('root'));
        super.destroy();
    }
}
