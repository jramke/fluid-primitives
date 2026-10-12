import { createProps } from '@zag-js/types';
import { createSplitProps } from '@zag-js/utils';
import type { ButtonProps } from './button.types';

export const props = createProps<ButtonProps>()([
    'id',
    'ids',
    'disabled',
    'loading',
    'focusableWhenDisabled',
]);
export const splitProps = createSplitProps<Partial<ButtonProps>>(props);
