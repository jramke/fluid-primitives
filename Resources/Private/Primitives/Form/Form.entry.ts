import * as v from 'valibot';
import { initAllComponentInstances } from '../../Client';
import { Form } from './Form';

(() => {
	initAllComponentInstances('form', ({ props }) => {
		const form = new Form({
			id: props.id,
			schema: v.object({
				firstName: v.pipe(
					v.string(),
					v.minLength(4, 'First name must be at least 4 characters long')
				),
				// checkboxExample: v.pipe(v.string(), v.value('on')),
				checkboxExample: v.pipe(v.string(), v.value('yes')),
				selectExample: v.string(),
			}),
			onSubmit: values => {
				alert(`Form submitted with values:\n${JSON.stringify(values, null, 2)}`);
				return new Promise<boolean>(resolve => {
					setTimeout(() => resolve(true), 1000);
				});
			},
		});
		form.init();
		return form;
	});
})();
