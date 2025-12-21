import { mount } from '../../Client';
import { Popover } from './Popover';

(() => {
	mount('popover', ({ props }) => {
		const popover = new Popover(props);
		popover.init();
		return popover;
	});
})();
