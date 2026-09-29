import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { TextareaProps } from './textarea.types';

export const props = createProps<TextareaProps>()([
    'id',
    'ids',
    'name',
    'disabled',
    'readOnly',
    'required',
    'invalid',
    'defaultValue',
    'maxLength',
    'rows',
    'submitOn',
    'translations',
    'announceDebounce',
    'transform',
    'onValueChange',
]);
export const splitProps = createSplitProps<Partial<TextareaProps>>(props);
