import * as numberInput from '@zag-js/number-input';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
    type WithWireTranslations,
} from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

type NumberInputProps = WithWireTranslations<numberInput.Props>;

// PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
// prop (see WireTypeResolver), so `formatOptions` resolves to `unknown` on the wire - this converter
// just tells TS what it actually is (a real Intl.NumberFormatOptions object) rather than
// transforming the value itself.
const numberInputPropConverters = {
    formatOptions: (formatOptions: numberInput.Props['formatOptions']) => formatOptions,
} satisfies ClientPropConverterMap;

registerClientPropConverters('numberInput', numberInputPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        numberInput: ConverterMachineProps<typeof numberInputPropConverters>;
    }
}

export class NumberInput extends FieldAwareComponent<NumberInputProps, numberInput.Api> {
    static componentName = 'numberInput';

    propsWithField(props: NumberInputProps, fieldMachine: FieldMachine): NumberInputProps {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    transformProps(props: NumberInputProps): NumberInputProps {
        return {
            ...props,
            onValueChange: details => {
                this.getElement('input')?.dispatchEvent(new Event('input', { bubbles: true }));
                props?.onValueChange?.(details);
            },
        };
    }

    initMachine(props: NumberInputProps): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(numberInput.machine, {
            ...this.transformProps(props),
            // Our own translations carry a `string | false` "disable this label" convention render()
            // already applies via userProps below - zag's own translations only ever accept
            // `string | undefined`, and blanking it here (rather than forwarding ours as-is) avoids
            // feeding zag's internal aria-label default a shape it was never meant to see.
            translations: undefined,
        });
    }

    initApi() {
        return numberInput.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const inputEl = this.getElement('input');
        if (inputEl) {
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        const incrementTriggerEl = this.getElement('incrementTrigger');
        if (incrementTriggerEl) {
            const triggerProps = mergeProps(this.api.getIncrementTriggerProps(), {
                'aria-label': this.userProps?.translations?.incrementLabel || null,
            });
            this.spreadProps(incrementTriggerEl, triggerProps);
        }

        const decrementTriggerEl = this.getElement('decrementTrigger');
        if (decrementTriggerEl) {
            const triggerProps = mergeProps(this.api.getDecrementTriggerProps(), {
                'aria-label': this.userProps?.translations?.decrementLabel || null,
            });
            this.spreadProps(decrementTriggerEl, triggerProps);
        }

        const valueTextEl = this.getElement('valueText');
        if (valueTextEl) this.spreadProps(valueTextEl, this.api.getValueTextProps());

        const scrubberEl = this.getElement('scrubber');
        if (scrubberEl) this.spreadProps(scrubberEl, this.api.getScrubberProps());
    }
}
