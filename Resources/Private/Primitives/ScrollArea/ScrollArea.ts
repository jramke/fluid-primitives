import * as scrollArea from '@zag-js/scroll-area';
import type { Orientation, PropTypes } from '@zag-js/types';
import { Component, Machine, normalizeProps } from '../../Client';

export class ScrollArea extends Component<scrollArea.Props, scrollArea.Api<PropTypes>> {
    static componentName = 'scrollArea';

    initMachine(props: scrollArea.Props): Machine<any> {
        const [machineProps] = scrollArea.splitProps(props);
        return new Machine(scrollArea.machine, machineProps);
    }

    initApi() {
        return scrollArea.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const viewportEl = this.hydrator.query('viewport');
        if (viewportEl) this.spreadProps(viewportEl, this.api.getViewportProps());

        const contentEl = this.hydrator.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const scrollbarEls = this.hydrator.queryAll('scrollbar');
        scrollbarEls.forEach(scrollbarEl => {
            this.spreadProps(
                scrollbarEl,
                this.api.getScrollbarProps({
                    orientation: scrollbarEl.getAttribute('data-orientation') as Orientation,
                })
            );
        });

        const cornerEl = this.hydrator.query('corner');
        if (cornerEl) this.spreadProps(cornerEl, this.api.getCornerProps());

        const thumbEls = this.hydrator.queryAll('thumb');
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
