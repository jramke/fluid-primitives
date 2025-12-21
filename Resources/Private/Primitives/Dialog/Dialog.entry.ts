import { mount } from '../../Client';
import { Dialog } from './Dialog';

(() => {
	mount('dialog', ({ props }) => {
		const dialog = new Dialog(props);
		dialog.init();
		return dialog;
	});
})();
