import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('form').parts(
    'form',
    'content',
    'indicator',
    'errorText',
    'successText'
);
export const parts = anatomy.build();
