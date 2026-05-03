import * as clipboard from '@zag-js/clipboard';
import { Component, Machine, mergeProps, normalizeProps } from '../../Client';

type ClipboardTranslations = {
	triggerLabelIdle?: string | null | false;
	triggerLabelCopied?: string | null | false;
};

export class Clipboard extends Component<clipboard.Props, clipboard.Api> {
	static name = 'clipboard';

	initMachine(props: clipboard.Props): Machine<any> {
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

		const indicatorCopiedEl = this.getElement('indicator-copied');
		if (indicatorCopiedEl)
			this.spreadProps(indicatorCopiedEl, this.api.getIndicatorProps({ copied: true }));

		const indicatorIdleEl = this.getElement('indicator-idle');
		if (indicatorIdleEl)
			this.spreadProps(indicatorIdleEl, this.api.getIndicatorProps({ copied: false }));

		const triggerEl = this.getElement('trigger');
		if (triggerEl) {
			const translations = this.userProps?.translations as ClipboardTranslations | undefined;
			const mergedProps = mergeProps(this.api.getTriggerProps(), {
				'aria-label': this.api.copied
					? translations?.triggerLabelCopied || null
					: translations?.triggerLabelIdle || null,
			});

			this.spreadProps(triggerEl, mergedProps);
		}
	}
}
