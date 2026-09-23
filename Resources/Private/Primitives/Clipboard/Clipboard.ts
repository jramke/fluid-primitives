import * as clipboard from '@zag-js/clipboard';
import { Component, Machine, mergeProps, normalizeProps } from '../../Client';

// Zag's own `translations` is a single `triggerLabel: (copied) => string` callback - Fluid can't
// author a callback, so `ClipboardContext::getTranslations()` sends two plain strings instead
// (`triggerLabelIdle`/`triggerLabelCopied`), picked between in render() below rather than forwarded
// to Zag's machine as-is.
type ClipboardProps = Omit<clipboard.Props, 'translations'> & {
    translations?: { triggerLabelIdle?: string; triggerLabelCopied?: string };
};

export class Clipboard extends Component<ClipboardProps, clipboard.Api> {
    static componentName = 'clipboard';

    initMachine(props: ClipboardProps): Machine<any> {
        return new Machine(clipboard.machine, { ...props, translations: undefined });
    }

    initApi() {
        return clipboard.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const inputEl = this.getElement('input');
        if (inputEl) this.spreadProps(inputEl, this.api.getInputProps());

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps({ copied: value === 'copied' })
        );

        const triggerEl = this.getElement('trigger');
        if (triggerEl) {
            const translations = this.userProps?.translations;
            const mergedProps = mergeProps(this.api.getTriggerProps(), {
                'aria-label': this.api.copied
                    ? translations?.triggerLabelCopied || null
                    : translations?.triggerLabelIdle || null,
            });

            this.spreadProps(triggerEl, mergedProps);
        }
    }
}
