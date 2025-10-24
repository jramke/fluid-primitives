import * as accordion from '@zag-js/accordion';
import { Component, Machine, normalizeProps } from '../../Client';

export class Accordion extends Component<accordion.Props, accordion.Api> {
	name = 'accordion';

	initMachine(props: accordion.Props): Machine<any> {
		return new Machine(accordion.machine, {
			collapsible: true,
			...props,
		});
	}

	initApi() {
		return accordion.connect(this.machine.service, normalizeProps);
	}

	render() {
		const rootEl = this.getElement('root');
		if (rootEl) {
			this.spreadProps(rootEl, this.api.getRootProps());
		}

		const itemEls = this.getElements('item');
		itemEls.forEach(itemEl => {
			this.spreadProps(
				itemEl,
				this.api.getItemProps({
					value: itemEl.getAttribute('data-value')!,
					disabled: itemEl.getAttribute('data-disabled') === 'true',
				})
			);
		});

		const triggers = this.getElements('item-trigger');
		triggers.forEach(trigger => {
			this.spreadProps(
				trigger,
				this.api.getItemTriggerProps({
					value: trigger.getAttribute('data-value')!,
					disabled: trigger.getAttribute('data-disabled') === 'true',
				})
			);
		});

		const contentEls = this.getElements('item-content');
		contentEls.forEach(contentEl => {
			this.spreadProps(
				contentEl,
				this.api.getItemContentProps({
					value: contentEl.getAttribute('data-value')!,
					disabled: contentEl.getAttribute('data-disabled') === 'true',
				})
			);
		});

		const indicatorEls = this.getElements('item-indicator');
		indicatorEls.forEach(indicatorEl => {
			this.spreadProps(
				indicatorEl,
				this.api.getItemIndicatorProps({
					value: indicatorEl.getAttribute('data-value')!,
					disabled: indicatorEl.getAttribute('data-disabled') === 'true',
				})
			);
		});

		// just so they are hydrated (data-attributes removed)
		this.getElements('item-header');
	}
}
