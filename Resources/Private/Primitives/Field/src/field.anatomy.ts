/**
 * Derived from @zag-js/field of Zag.js (https://github.com/chakra-ui/zag, packages/machines/field,
 * commit 0ce8e3de63b571d37e438bcf28e64759466e56e9), MIT License, Copyright (c) 2021 Chakra UI.
 * Adapted for Fluid Primitives, the git history shows what changed from the original.
 */

import { createAnatomy } from '@zag-js/anatomy';

export const anatomy = createAnatomy('field').parts(
    'root',
    'label',
    'control',
    'helperText',
    'errorText',
    'indicator'
);

export const parts = anatomy.build();
