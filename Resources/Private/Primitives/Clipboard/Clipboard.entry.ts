import { mount } from '../../Client';
import { Clipboard } from './Clipboard';

(() => {
	mount('clipboard', ({ props }) => {
		const clipboard = new Clipboard(props);
		clipboard.init();
		return clipboard;
	});
})();
