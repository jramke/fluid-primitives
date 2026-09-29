import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { InputProps } from './input.types';

export const props = createProps<InputProps>()([
    'id',
    'ids',
    'name',
    'disabled',
    'readOnly',
    'required',
    'invalid',
    'defaultValue',
    'maxLength',
    'pattern',
    'inputMode',
    'translations',
    'announceDebounce',
    'transform',
    'onValueChange',
]);
export const splitProps = createSplitProps<Partial<InputProps>>(props);
