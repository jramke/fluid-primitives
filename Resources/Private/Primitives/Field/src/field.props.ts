import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { FieldProps } from './field.types';

export const props = createProps<FieldProps>()([
    'id',
    'ids',
    'name',
    'invalid',
    'required',
    'disabled',
    'readOnly',
    'defaultValue',
    'listenTo',
]);
export const splitProps = createSplitProps<Partial<FieldProps>>(props);
