import { createLiveRegion } from '@zag-js/live-region';
import { debounce } from '@zag-js/utils';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';
import { connect } from './src/input.connect';
import * as dom from './src/input.dom';
import { machine } from './src/input.machine';
import type { InputApi, InputProps } from './src/input.types';

const ANNOUNCE_DEBOUNCE_MS = 600;

export class Input extends FieldAwareComponent<InputProps, InputApi> {
    static componentName = 'input';

    private liveRegion: ReturnType<typeof createLiveRegion> | null = null;
    private announceCountChange = debounce((text: string) => {
        this.liveRegion?.announce(text);
    }, ANNOUNCE_DEBOUNCE_MS);

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

        const inputEl = this.getElement<HTMLInputElement>('input');
        if (inputEl) {
            const describeIds = [this.fieldMachine?.context.get('describeIds'), wordCountId]
                .filter(Boolean)
                .join(' ');
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': describeIds || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        if (wordCountEl) {
            this.spreadProps(wordCountEl, this.api.getWordCountProps());
            wordCountEl.textContent = this.api.countText ?? '';
        }

        const liveRegionEl = this.getElement<HTMLElement>('liveRegion');
        if (liveRegionEl) {
            this.spreadProps(liveRegionEl, this.api.getLiveRegionProps());
            if (!this.liveRegion) {
                this.liveRegion = createLiveRegion({ level: 'polite', root: liveRegionEl });
            }
            if (this.api.countText) this.announceCountChange(this.api.countText);
        }
    }

    destroy() {
        this.liveRegion?.destroy();
        super.destroy();
    }
}
