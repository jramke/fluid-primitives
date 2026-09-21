import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/form.connect';
import { machine } from './src/form.machine';
import { getFieldMachinesFor, registerFormMachine, type FieldMachine } from './src/form.registry';
import type { FormApi, FormProps, FormState } from './src/form.types';
export type {
    FormApi,
    FormErrors,
    FormProps,
    FormSubmitResult,
    FormValidation,
    FormValueLeaf,
    FormValues,
    FormValuesObject,
    FormValueTree,
} from './src/form.types';

export class Form extends Component<FormProps, FormApi> {
    static componentName = 'form';

    private fieldSubscriptions = new Map<FieldMachine, () => void>();

    initMachine(props: FormProps) {
        const createdMachine = new Machine(machine, props);
        registerFormMachine(this.getElement('root'), createdMachine);
        return createdMachine;
    }

    initApi() {
        return connect(this.machine.service, normalizeProps);
    }

    private subscribeToFieldMachines(formEl: HTMLFormElement) {
        for (const fieldMachine of getFieldMachinesFor(formEl).values()) {
            if (this.fieldSubscriptions.has(fieldMachine)) continue;

            const unsubscribe = fieldMachine.subscribe(() => {
                this.api = this.initApi();
                this.render();
            });

            this.fieldSubscriptions.set(fieldMachine, unsubscribe);
        }
    }

    render() {
        const formEl = this.getElement('root') as HTMLFormElement | null;
        if (!formEl) return;

        this.subscribeToFieldMachines(formEl);

        this.spreadProps(formEl, this.api.getFormProps());

        this.getElements('content').forEach(contentEl => {
            this.spreadProps(contentEl, this.api.getContentProps());
        });

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps(value as FormState)
        );

        this.getElements('errorText').forEach(errorTextEl => {
            this.spreadProps(errorTextEl, this.api.getErrorTextProps());
            syncStatusText(errorTextEl, this.api.getErrorText());
        });

        this.getElements('successText').forEach(successTextEl => {
            this.spreadProps(successTextEl, this.api.getSuccessTextProps());
            syncStatusText(successTextEl, this.api.getSuccessText());
        });

        this.api._userRenderFn?.(this);
    }

    destroy() {
        for (const unsubscribe of this.fieldSubscriptions.values()) {
            unsubscribe();
        }
        this.fieldSubscriptions.clear();
        super.destroy();
    }
}

function syncStatusText(element: HTMLElement, text: string | null) {
    if (element.dataset.defaultText === undefined) {
        element.dataset.defaultText = element.textContent ?? '';
    }

    element.textContent = text ?? element.dataset.defaultText;
}
