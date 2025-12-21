import { mount } from '../../Client';
import { Field } from './Field';

(() => {
	mount('field', ({ props }) => {
		// @ts-expect-error
		const field = new Field(props);
		field.init();
		return field;
	});
})();
