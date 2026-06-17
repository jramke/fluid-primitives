import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/form.connect';
import { machine } from './src/form.machine';
import { registerFormMachine } from './src/form.registry';
import type { FormApi, FormProps, FormState } from './src/form.types';

const formStates: FormState[] = ['ready', 'invalid', 'submitting', 'success', 'error'];

export class Form extends Component<FormProps, FormApi> {
	static name = 'form';

	initMachine(props: FormProps) {
		const createdMachine = new Machine(machine, props);
		registerFormMachine(this.getElement('form'), createdMachine);
		return createdMachine;
	}

	initApi() {
		return connect(this.machine.service, normalizeProps);
	}

	render() {
		const formEl = this.getElement('form') as HTMLFormElement | null;
		if (!formEl) return;

		this.spreadProps(formEl, this.api.getFormProps());

		this.getElements('content').forEach(contentEl => {
			this.spreadProps(contentEl, this.api.getContentProps());
		});

		formStates.forEach(state => {
			this.getElements(`indicator-${state}`).forEach(indicatorEl => {
				this.spreadProps(indicatorEl, this.api.getIndicatorProps(state));
			});
		});

		this.getElements('error-text').forEach(errorTextEl => {
			this.spreadProps(errorTextEl, this.api.getErrorTextProps());
			syncStatusText(errorTextEl, this.api.getErrorText());
		});

		this.getElements('success-text').forEach(successTextEl => {
			this.spreadProps(successTextEl, this.api.getSuccessTextProps());
			syncStatusText(successTextEl, this.api.getSuccessText());
		});

		this.api._userRenderFn?.(this);
	}
}

function syncStatusText(element: HTMLElement, text: string | null) {
	if (element.dataset.defaultText === undefined) {
		element.dataset.defaultText = element.textContent ?? '';
	}

	element.textContent = text ?? element.dataset.defaultText;
}
