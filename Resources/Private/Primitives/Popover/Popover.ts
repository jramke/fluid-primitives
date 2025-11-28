import * as popover from '@zag-js/popover';
import { Component, Machine, normalizeProps } from '../../Client';

export class Popover extends Component<popover.Props, popover.Api> {
	name = 'popover';

	initMachine(props: popover.Props): Machine<any> {
		return new Machine(popover.machine, {
			...props,
			positioning: {
				gutter: 6,
				...props.positioning,
			},
		});
	}

	initApi() {
		return popover.connect(this.machine.service, normalizeProps);
	}

	render() {
		const triggerEl = this.getElement('trigger');
		if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

		const positionerEl = this.getElement('positioner');
		if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

		const arrowEl = this.getElement('arrow');
		if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

		const arrowTipEl = this.getElement('arrow-tip');
		if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

		const contentEl = this.getElement('content');
		if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

		const titleEl = this.getElement('title');
		if (titleEl) this.spreadProps(titleEl, this.api.getTitleProps());

		const descriptionEl = this.getElement('description');
		if (descriptionEl) this.spreadProps(descriptionEl, this.api.getDescriptionProps());

		const closeTriggerEl = this.getElement('close-trigger');
		if (closeTriggerEl) this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
	}
}
