import { createAnatomy } from '@zag-js/anatomy';

const anatomy = createAnatomy('form').parts(
	'form',
	'content',
	'indicator',
	'error-text',
	'success-text'
);
export const parts = anatomy.build();
