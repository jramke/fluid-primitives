import * as scrollArea from '@zag-js/scroll-area';
import type { Orientation, PropTypes } from '@zag-js/types';
import { Component, Machine, normalizeProps } from '../../Client';

export class ScrollArea extends Component<scrollArea.Props, scrollArea.Api<PropTypes>> {
	static name = 'scroll-area';

	initMachine(props: scrollArea.Props): Machine<any> {
		return new Machine(scrollArea.machine, props);
	}

	initApi() {
		return scrollArea.connect(this.machine.service, normalizeProps);
	}

	render() {
		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const viewportEl = this.getElement('viewport');
		if (viewportEl) this.spreadProps(viewportEl, this.api.getViewportProps());

		const contentEl = this.getElement('content');
		if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

		const scrollbarEls = this.getElements('scrollbar');
		scrollbarEls.forEach(scrollbarEl => {
			this.spreadProps(
				scrollbarEl,
				this.api.getScrollbarProps({
					orientation: scrollbarEl.getAttribute('data-orientation') as Orientation,
				})
			);
		});

		const cornerEl = this.getElement('corner');
		if (cornerEl) this.spreadProps(cornerEl, this.api.getCornerProps());

		const thumbEls = this.getElements('thumb');
		thumbEls.forEach(thumbEl => {
			this.spreadProps(
				thumbEl,
				this.api.getThumbProps({
					orientation: thumbEl.getAttribute('data-orientation') as Orientation,
				})
			);
		});
	}
}
