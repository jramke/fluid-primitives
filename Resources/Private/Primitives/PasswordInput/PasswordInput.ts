import * as passwordInput from '@zag-js/password-input';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';
import {
    resolveTranslations,
    type PasswordInputTranslationTexts,
} from './src/password-input.translations';

// `visibilityTrigger` is a function of the visible state in Zag, a label per state on the wire.
const passwordInputPropConverters = {
    translations: (texts: PasswordInputTranslationTexts | undefined) => resolveTranslations(texts),
} satisfies ClientPropConverterMap;

registerClientPropConverters('passwordInput', passwordInputPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        passwordInput: ConverterMachineProps<typeof passwordInputPropConverters>;
    }
}

export class PasswordInput extends FieldAwareComponent<passwordInput.Props, passwordInput.Api> {
    static componentName = 'passwordInput';

    propsWithField(props: passwordInput.Props, field: FieldClientApi): passwordInput.Props {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: passwordInput.Props): Machine<any> {
        props = this.withFieldProps(props);
        const [machineProps] = passwordInput.splitProps(props);
        return new Machine(passwordInput.machine, machineProps);
    }

    initApi() {
        return passwordInput.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        // not spreadTextControlProps: the machine has no value, which would empty what was typed or autofilled
        const inputEl = this.hydrator.query('input');
        if (inputEl) {
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': this.field?.ariaDescribedby,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        const visibilityTriggerEl = this.hydrator.query('visibilityTrigger');
        if (visibilityTriggerEl)
            this.spreadProps(visibilityTriggerEl, this.api.getVisibilityTriggerProps());

        this.spreadPropsByValue('indicator', ({ value }) => ({
            ...this.api.getIndicatorProps(),
            hidden: (value === 'visible') === this.api.visible ? undefined : true,
        }));
    }
}
