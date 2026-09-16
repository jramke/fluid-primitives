import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';
import { connect } from './src/textarea.connect';
import * as dom from './src/textarea.dom';
import { machine } from './src/textarea.machine';
import type { TextareaApi, TextareaProps } from './src/textarea.types';

export class Textarea extends FieldAwareComponent<TextareaProps, TextareaApi> {
    static componentName = 'textarea';

    propsWithField(props: TextareaProps, fieldMachine: FieldMachine): TextareaProps {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: TextareaProps): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(machine, props);
    }

    initApi() {
        return connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const wordCountEl = this.getElement('wordCount');
        const wordCountId = wordCountEl ? dom.getWordCountId(this.machine.scope) : undefined;

        const textareaEl = this.getElement<HTMLTextAreaElement>('textarea');
        if (textareaEl) {
            const describeIds = [this.fieldMachine?.context.get('describeIds'), wordCountId]
                .filter(Boolean)
                .join(' ');
            const mergedProps = mergeProps(this.api.getTextareaProps(), {
                'aria-describedby': describeIds || undefined,
            });
            this.spreadProps(textareaEl, mergedProps);
        }

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        if (wordCountEl) {
            this.spreadProps(wordCountEl, this.api.getWordCountProps());
            wordCountEl.textContent = this.api.countText ?? '';
        }

        const liveRegionEl = this.getElement<HTMLElement>('liveRegion');
        if (liveRegionEl) this.spreadProps(liveRegionEl, this.api.getLiveRegionProps());
    }
}
