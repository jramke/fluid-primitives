import { initAllComponentInstances } from '../../Client';
import { Field } from './Field';

(() => {
	initAllComponentInstances('field', ({ props }) => {
		// @ts-expect-error
		const field = new Field(props);
		field.init();
		return field;
	});
})();
