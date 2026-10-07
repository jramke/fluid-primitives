import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';
import { connect } from './src/input.connect';
import * as dom from './src/input.dom';
import { machine } from './src/input.machine';
import { splitProps } from './src/input.props';
import type { InputApi, InputProps } from './src/input.types';

export class Input extends FieldAwareComponent<InputProps, InputApi> {
    static componentName = 'input';

    propsWithField(props: InputProps, field: FieldClientApi): InputProps {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
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

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const wordCountEl = this.hydrator.query('wordCount');
        const wordCountId = wordCountEl ? dom.getWordCountId(this.machine.scope) : undefined;

        const inputEl = this.hydrator.query<HTMLInputElement>('input');
        if (inputEl) {
            const describeIds = [this.field?.ariaDescribedby, wordCountId]
                .filter(Boolean)
                .join(' ');
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': describeIds || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        if (wordCountEl) {
            this.spreadProps(wordCountEl, this.api.getWordCountProps());
            wordCountEl.textContent = this.api.countText ?? '';
        }

        const liveRegionEl = this.hydrator.query<HTMLElement>('liveRegion');
        if (liveRegionEl) this.spreadProps(liveRegionEl, this.api.getLiveRegionProps());
    }
}
