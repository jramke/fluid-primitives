import { initAllComponentInstances } from '../../Client';
import { Clipboard } from './Clipboard';

(() => {
	initAllComponentInstances('clipboard', ({ props }) => {
		const clipboard = new Clipboard(props);
		clipboard.init();
		return clipboard;
	});
})();
