import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { CheckboxGroupProps } from './checkbox-group.types';

export const props = createProps<CheckboxGroupProps>()([
    'id',
    'ids',
    'defaultValue',
    'value',
    'name',
    'form',
    'disabled',
    'readOnly',
    'required',
    'invalid',
    'maxSelectedValues',
    'onValueChange',
]);
export const splitProps = createSplitProps<Partial<CheckboxGroupProps>>(props);
