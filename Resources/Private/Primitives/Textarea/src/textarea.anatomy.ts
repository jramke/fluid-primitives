import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('textarea').parts('root', 'label', 'textarea', 'wordCount');
export const parts = anatomy.build();
