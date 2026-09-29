import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';
import { connect } from './src/input.connect';
import * as dom from './src/input.dom';
import { machine } from './src/input.machine';
import { splitProps } from './src/input.props';
import type { InputApi, InputProps } from './src/input.types';

export class Input extends FieldAwareComponent<InputProps, InputApi> {
    static componentName = 'input';

    propsWithField(props: InputProps, fieldMachine: FieldMachine): InputProps {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: InputProps): Machine<any> {
        props = this.withFieldProps(props);
        const [machineProps] = splitProps(props);
        return new Machine(machine, machineProps);
    }

    initApi() {
        return connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const wordCountEl = this.query('wordCount');
        const wordCountId = wordCountEl ? dom.getWordCountId(this.machine.scope) : undefined;

        const inputEl = this.query<HTMLInputElement>('input');
        if (inputEl) {
            const describeIds = [this.fieldMachine?.context.get('describeIds'), wordCountId]
                .filter(Boolean)
                .join(' ');
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': describeIds || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        const labelEl = this.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        if (wordCountEl) {
            this.spreadProps(wordCountEl, this.api.getWordCountProps());
            wordCountEl.textContent = this.api.countText ?? '';
        }

        const liveRegionEl = this.query<HTMLElement>('liveRegion');
        if (liveRegionEl) this.spreadProps(liveRegionEl, this.api.getLiveRegionProps());
    }
}
