import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('field-array').parts('root', 'addTrigger', 'removeTrigger');
export const parts = anatomy.build();
