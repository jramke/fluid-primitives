import * as tabs from '@zag-js/tabs';
import { Component, Machine, normalizeProps } from '../../Client';

export class Tabs extends Component<tabs.Props, tabs.Api> {
	static name = 'tabs';

	initMachine(props: tabs.Props): Machine<any> {
		return new Machine(tabs.machine, props);
	}

	initApi() {
		return tabs.connect(this.machine.service, normalizeProps);
	}

	render = () => {
		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const listEl = this.getElement('list');
		if (listEl) this.spreadProps(listEl, this.api.getListProps());

		const triggerEls = this.getElements('trigger');
		triggerEls.forEach(triggerEl => {
			this.spreadProps(
				triggerEl,
				this.api.getTriggerProps({
					value: triggerEl.dataset.value!,
					disabled: triggerEl.getAttribute('data-disabled') === 'true',
				})
			);
		});

		const contentEls = this.getElements('content');
		contentEls.forEach(contentEl => {
			this.spreadProps(
				contentEl,
				this.api.getContentProps({
					value: contentEl.dataset.value!,
				})
			);
		});

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) {
			this.spreadProps(indicatorEl, this.api.getIndicatorProps());
		}
	};
}
