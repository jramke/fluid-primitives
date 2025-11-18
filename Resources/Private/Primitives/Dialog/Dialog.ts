import * as dialog from '@zag-js/dialog';
import { Component, Machine, normalizeProps } from '../../Client';

export class Dialog extends Component<dialog.Props, dialog.Api> {
	static name = 'dialog';

	initMachine(props: dialog.Props): Machine<any> {
		return new Machine(dialog.machine, props);
	}

	initApi() {
		return dialog.connect(this.machine.service, normalizeProps);
	}

	render() {
		const triggers = this.getElements('trigger');
		triggers.forEach(trigger => {
			this.spreadProps(trigger, this.api.getTriggerProps());
		});

		const backdropEl = this.getElement('backdrop');
		if (backdropEl) {
			this.spreadProps(backdropEl, this.api.getBackdropProps());
		}

		const positionerEl = this.getElement('positioner');
		if (positionerEl) {
			this.spreadProps(positionerEl, this.api.getPositionerProps());
		}

		const contentEl = this.getElement('content');
		if (contentEl) {
			this.spreadProps(contentEl, this.api.getContentProps());
		}

		const titleEl = this.getElement('title');
		if (titleEl) {
			this.spreadProps(titleEl, this.api.getTitleProps());
		}

		const descriptionEl = this.getElement('description');
		if (descriptionEl) {
			this.spreadProps(descriptionEl, this.api.getDescriptionProps());
		}

		const closeTriggers = this.getElements('close-trigger');
		closeTriggers.forEach(trigger => {
			this.spreadProps(trigger, this.api.getCloseTriggerProps());
		});
	}
}
