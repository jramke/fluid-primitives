import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('input').parts('root', 'label', 'input', 'wordCount', 'liveRegion');
export const parts = anatomy.build();
