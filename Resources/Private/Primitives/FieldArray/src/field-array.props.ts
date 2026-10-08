import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { FieldArrayProps } from './field-array.types';

export const props = createProps<FieldArrayProps>()([
    'id',
    'name',
    'itemCount',
    'minItems',
    'maxItems',
    'translations',
    'onItemAdded',
    'onItemRemoved',
]);
export const splitProps = createSplitProps<Partial<FieldArrayProps>>(props);
