import { initAllComponentInstances } from '../../Client';
import { Form } from './Form';

(() => {
	initAllComponentInstances('form', ({ props }) => {
		const form = new Form(props);
		form.init();
		return form;
	});
})();
