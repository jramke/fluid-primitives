import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('button').parts('root', 'indicator');
export const parts = anatomy.build();
