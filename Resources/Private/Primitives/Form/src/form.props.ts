import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { FormProps } from './form.types';

export const props = createProps<FormProps>()([
    'id',
    'validation',
    'objectName',
    'inputDebounceMs',
    'onSubmit',
    'render',
]);
export const splitProps = createSplitProps<Partial<FormProps>>(props);
