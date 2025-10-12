import { initAllComponentInstances } from '../../Client';
import { Dialog } from './Dialog';

(() => {
	initAllComponentInstances('dialog', ({ props }) => {
		const dialog = new Dialog(props);
		dialog.init();
		return dialog;
	});
})();
