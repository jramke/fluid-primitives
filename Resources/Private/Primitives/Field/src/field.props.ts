import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { FieldProps } from './field.types';

export const props = createProps<FieldProps>()([
    'defaultValue',
    'dir',
    'dirty',
    'disabled',
    'getRootNode',
    'id',
    'ids',
    'invalid',
    'listenTo',
    'name',
    'onValidityChange',
    'readOnly',
    'required',
    'touched',
    'validate',
    'validationMode',
]);
export const splitProps = createSplitProps<Partial<FieldProps>>(props);
