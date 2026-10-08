/**
 * Derived from @zag-js/field of Zag.js (https://github.com/chakra-ui/zag, packages/machines/field,
 * commit 0ce8e3de63b571d37e438bcf28e64759466e56e9), MIT License, Copyright (c) 2021 Chakra UI.
 * Adapted for Fluid Primitives, the git history shows what changed from the original.
 */

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
