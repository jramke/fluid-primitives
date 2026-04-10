import * as navigationMenu from '@zag-js/navigation-menu';
import { Component, Machine, normalizeProps } from '../../Client';

export class NavigationMenu extends Component<navigationMenu.Props, navigationMenu.Api> {
	static name = 'navigation-menu';

	initMachine(props: navigationMenu.Props): Machine<any> {
		return new Machine(navigationMenu.machine, props);
	}

	initApi() {
		return navigationMenu.connect(this.machine.service, normalizeProps);
	}

	render() {
		const rootEl = this.getElement('root');
		console.log(this.api.getRootProps());

		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const listEl = this.getElement('list');
		if (listEl) this.spreadProps(listEl, this.api.getListProps());

		// hydrate indicator-track wrapper (no specific Zag API, just remove data-hydrate attributes)
		this.getElement('indicator-track');

		const itemEls = this.getElements('item');
		itemEls.forEach(itemEl => {
			this.spreadProps(
				itemEl,
				this.api.getItemProps({
					value: itemEl.dataset.value!,
					disabled: itemEl.hasAttribute('data-disabled'),
				})
			);
		});

		const triggerEls = this.getElements('trigger');
		triggerEls.forEach(triggerEl => {
			this.spreadProps(
				triggerEl,
				this.api.getTriggerProps({
					value: triggerEl.dataset.value!,
					disabled: triggerEl.hasAttribute('data-disabled'),
				})
			);
		});

		const triggerProxyEls = this.getElements('trigger-proxy');
		triggerProxyEls.forEach(triggerProxyEl => {
			this.spreadProps(
				triggerProxyEl,
				this.api.getTriggerProxyProps({
					value: triggerProxyEl.dataset.value!,
				})
			);
		});

		const viewportProxyEls = this.getElements('viewport-proxy');
		viewportProxyEls.forEach(viewportProxyEl => {
			this.spreadProps(
				viewportProxyEl,
				this.api.getViewportProxyProps({
					value: viewportProxyEl.dataset.value!,
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

		const linkEls = this.getElements('link');
		linkEls.forEach(linkEl => {
			this.spreadProps(
				linkEl,
				this.api.getLinkProps({
					value: linkEl.dataset.value!,
					current: linkEl.hasAttribute('data-current'),
				})
			);
		});

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

		const arrowEl = this.getElement('arrow');
		if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

		const viewportPositionerEl = this.getElement('viewport-positioner');
		if (viewportPositionerEl) {
			const align = (viewportPositionerEl.dataset.align ||
				undefined) as navigationMenu.ViewportProps['align'];
			this.spreadProps(viewportPositionerEl, this.api.getViewportPositionerProps({ align }));
		}

		const viewportEl = this.getElement('viewport');
		if (viewportEl) {
			const align = (viewportEl.dataset.align ||
				undefined) as navigationMenu.ViewportProps['align'];
			this.spreadProps(viewportEl, this.api.getViewportProps({ align }));
		}

		const itemIndicatorEls = this.getElements('item-indicator');
		itemIndicatorEls.forEach(itemIndicatorEl => {
			this.spreadProps(
				itemIndicatorEl,
				this.api.getItemIndicatorProps({
					value: itemIndicatorEl.dataset.value!,
					disabled: itemIndicatorEl.hasAttribute('data-disabled'),
				})
			);
		});
	}
}
