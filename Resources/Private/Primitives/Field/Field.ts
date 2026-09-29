import { Component, Machine, normalizeProps } from '../../Client';
import { registerFieldMachineForForm } from '../Form/src/form.registry';
import { connect } from './src/field.connect';
import { machine } from './src/field.machine';
import { splitProps } from './src/field.props';
import { registerFieldMachine } from './src/field.registry';
import type { FieldApi, FieldProps } from './src/field.types';

export type {
    FieldDependencyChangeDetail,
    FieldHandle,
    FieldProps,
    FieldValue,
} from './src/field.types';

export class Field extends Component<FieldProps, FieldApi> {
    static componentName = 'field';

    initMachine(props: FieldProps) {
        const [machineProps] = splitProps(props);
        const createdMachine = new Machine(machine, machineProps);
        registerFieldMachine(this.query('root'), createdMachine);
        registerFieldMachineForForm(this.query('root'), createdMachine);
        return createdMachine;
    }

    initApi() {
        return connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.query('root');
        if (rootEl) {
            this.spreadProps(rootEl, this.api.getRootProps());
        }

        const labelEl = this.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.query('control');
        // For field-aware primitives, this id is shared with one of their own parts (see
        // ComponentUtility::FIELD_ID_PARTS) just so `<label for>` targets the right element - that
        // element already manages its own name/disabled/required/etc, so only apply ours when
        // `control` is the bare native element Field wraps directly (asChild).
        if (controlEl?.dataset.scope === 'field') {
            this.spreadProps(controlEl, this.api.getControlProps());
        }

        const descriptionEl = this.query('description');
        if (descriptionEl) this.spreadProps(descriptionEl, this.api.getDescriptionProps());

        const errorEl = this.query('error');
        if (errorEl) {
            this.spreadProps(errorEl, this.api.getErrorProps());
            const msg = this.api.getErrorText();
            if (msg) {
                errorEl.textContent = msg;
            }
        }
    }
}
