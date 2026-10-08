import { uuid } from '@zag-js/utils';

/**
 * Creates an id that is unique within the page, e.g. for a part that needs an `id` to reference.
 *
 * @param prefix - Starts the id.
 */
export function uid(prefix = 'f') {
    return '«' + prefix + uuid() + '»';
}
