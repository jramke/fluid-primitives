import { mount } from '../../Client';
import { ScrollArea } from './ScrollArea';

(() => {
	mount('scroll-area', ({ props }) => {
		const scrollArea = new ScrollArea(props);
		scrollArea.init();
		return scrollArea;
	});
})();
