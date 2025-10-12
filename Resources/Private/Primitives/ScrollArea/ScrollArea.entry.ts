import { initAllComponentInstances } from '../../Client';
import { ScrollArea } from './ScrollArea';

(() => {
	initAllComponentInstances('scroll-area', ({ props }) => {
		const scrollArea = new ScrollArea(props);
		scrollArea.init();
		return scrollArea;
	});
})();
