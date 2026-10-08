import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';
import { connect } from './src/textarea.connect';
import * as dom from './src/textarea.dom';
import { machine } from './src/textarea.machine';
import { splitProps } from './src/textarea.props';
import type { TextareaApi, TextareaProps } from './src/textarea.types';

export class Textarea extends FieldAwareComponent<TextareaProps, TextareaApi> {
    static componentName = 'textarea';

    propsWithField(props: TextareaProps, field: FieldClientApi): TextareaProps {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: TextareaProps): Machine<any> {
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

        const textareaEl = this.hydrator.query<HTMLTextAreaElement>('textarea');
        if (textareaEl) {
            const describeIds = [this.field?.ariaDescribedby, wordCountId]
                .filter(Boolean)
                .join(' ');
            const mergedProps = mergeProps(this.api.getTextareaProps(), {
                'aria-describedby': describeIds || undefined,
            });
            this.spreadTextControlProps(textareaEl, mergedProps);
        }

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        if (wordCountEl) {
            this.spreadProps(wordCountEl, this.api.getWordCountProps());
            wordCountEl.textContent = this.api.countText ?? '';
        }
    }
}
