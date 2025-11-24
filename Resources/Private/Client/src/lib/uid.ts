import { uuid } from '@zag-js/utils';

export function uid(prefix = 'f') {
	return '«' + prefix + uuid() + '»';
}
