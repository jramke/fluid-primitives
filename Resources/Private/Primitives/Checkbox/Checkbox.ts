import * as checkbox from '@zag-js/checkbox';
import { Component, Machine, normalizeProps } from '../../Client';

export class Checkbox extends Component<checkbox.Props, checkbox.Api> {
	name = 'checkbox';

	initMachine(props: checkbox.Props): Machine<any> {
		return new Machine(checkbox.machine, props);
	}

	initApi() {
		return checkbox.connect(this.machine.service, normalizeProps);
	}

	render() {
		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

		const hiddenInputEl = this.getElement('hidden-input');
		if (hiddenInputEl) this.spreadProps(hiddenInputEl, this.api.getHiddenInputProps());
	}
}
