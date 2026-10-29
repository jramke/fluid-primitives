import * as clipboard from '@zag-js/clipboard';
import { Component, Machine, normalizeProps } from '../../Client';
import { resolveTranslations, type ClipboardTranslationTexts } from './src/clipboard.translations';

type ClipboardProps = Omit<clipboard.Props, 'translations'> & {
    translations?: ClipboardTranslationTexts;
};

export class Clipboard extends Component<ClipboardProps, clipboard.Api> {
    static componentName = 'clipboard';

    initMachine(props: ClipboardProps): Machine<any> {
        const { translations, ...rest } = props;
        const [machineProps] = clipboard.contextProps({
            ...rest,
            translations: resolveTranslations(translations),
        });
        return new Machine(clipboard.machine, machineProps);
    }

    initApi() {
        return clipboard.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const inputEl = this.hydrator.query('input');
        if (inputEl) this.spreadProps(inputEl, this.api.getInputProps());

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps({ copied: value === 'copied' })
        );

        const triggerEl = this.hydrator.query('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());
    }
}
