import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('textarea').parts(
    'root',
    'label',
    'textarea',
    'wordCount',
    'liveRegion'
);
export const parts = anatomy.build();
