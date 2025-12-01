import * as v from 'valibot';
import { initAllComponentInstances } from '../../Client';
import { Form } from './Form';

(() => {
	initAllComponentInstances('form', ({ props }) => {
		const form = new Form({
			id: props.id,
			schema: v.object({
				something: v.pipe(
					v.string(),
					v.minLength(4, 'Something must be at least 4 characters long')
				),
				checkboxExample: v.pipe(v.string(), v.value('on')),
				selectExample: v.string(),
			}),
			onSubmit: async values => {
				// alert(`Form submitted with values:\n${JSON.stringify(values, null, 2)}`);
				console.log('submit', values);

				return new Promise<boolean>(resolve => {
					setTimeout(() => resolve(true), 1000);
				});
			},
			render: form => {
				console.log('custom render function called', {
					errors: form.api.getErrors(),
					values: form.api.getValues(),
					dirty: form.api.getDirty(),
					fields: form.api.getFields().size,
				});

				// form.api.getFormEl()?.appendChild(document.createTextNode(form.api.state));

				// TODO: maybe we can make a form.submitButton api
				const submitButton = form.api
					.getFormEl()
					?.querySelector('button[type="submit"]') as HTMLButtonElement | undefined;
				if (submitButton) {
					submitButton.disabled = form.api.isSubmitting;
				}
			},
			reactiveFields: ['checkboxExample'],
		});
		form.init();
		return form;
	});
})();
