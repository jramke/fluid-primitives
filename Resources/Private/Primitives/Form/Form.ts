import { revalidateLogic } from '@tanstack/form-core';
import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/form.connect';
import { machine } from './src/form.machine';
import { registerFormMachine } from './src/form.registry';
import { ValidationError, type FormApi, type FormProps } from './src/form.types';

export { revalidateLogic, ValidationError };

export class Form extends Component<FormProps, FormApi> {
	static name = 'form';

	initMachine(props: FormProps) {
		const createdMachine = new Machine(machine, props);
		registerFormMachine(this.getElement('form'), createdMachine, () =>
			createdMachine.refs.get('form')
		);
		return createdMachine;
	}

	initApi() {
		return connect(this.machine.service, normalizeProps);
	}

	render() {
		const formEl = this.getElement('form') as HTMLFormElement | null;
		if (!formEl) return;

		this.spreadProps(formEl, this.api.getFormProps());

		this.api._userRenderFn?.(this);
	}
}
