import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('field').parts('root', 'label', 'control', 'error', 'description');
export const parts = anatomy.build();
