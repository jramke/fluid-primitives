import { initAllComponentInstances } from '../../Client';
import { Popover } from './Popover';

(() => {
	initAllComponentInstances('popover', ({ props }) => {
		const popover = new Popover(props);
		popover.init();
		return popover;
	});
})();
