import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('field-array').parts('addTrigger', 'removeTrigger', 'liveRegion');
export const parts = anatomy.build();
