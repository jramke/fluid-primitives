import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('checkbox-group').parts('root', 'label');
export const parts = anatomy.build();
