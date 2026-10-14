import { mountAll } from 'fluid-primitives';
import { Form } from 'fluid-primitives/form';

mountAll('ui:form', ({ props }) => {
    const form = new Form({
        ...props,
        onSubmit: async ({ api, post }) => {
            let response: Response;
            try {
                response = await post(api.getAction());
            } catch (error) {
                // post() throws on a 422; the form maps those to field and form errors itself.
                if (error instanceof Error && ['ValidationError', 'FormError'].includes(error.name))
                    throw error;
                api.setErrorText(null);
                return false;
            }

            const data: { message?: string } = await response.json().catch(() => ({}));
            if (!response.ok) {
                api.setErrorText(data.message ?? null);
                return false;
            }

            api.setSuccessText(data.message ?? null);
            return true;
        },
    });
    form.init();
    return form;
});
