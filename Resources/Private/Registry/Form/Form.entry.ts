import { extbase, mountAll } from 'fluid-primitives';
import { Form } from 'fluid-primitives/form';

mountAll('ui:form', ({ props }) =>
    new Form({
        ...props,
        onSubmit: async ({ api, values }) => {
            const response = await extbase.post(api.getAction(), values.toObject(), {
                prefix: api.getFieldNamePrefix(),
                objectName: api.getObjectName(),
            });

            const data: { message?: string } = await response.json().catch(() => ({}));
            if (!response.ok) {
                api.setErrorText(data.message ?? null);
                return false;
            }

            api.setSuccessText(data.message ?? null);
            return true;
        },
    }).init()
);
