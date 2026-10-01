import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('form').parts(
    'root',
    'content',
    'indicator',
    'errorText',
    'successText'
);
export const parts = anatomy.build();
