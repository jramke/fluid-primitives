import { mount } from '../../Client';
import { Form } from './Form';

(() => {
	mount('form', ({ props }) => {
		const form = new Form(props);
		form.init();
		return form;
	});
})();
