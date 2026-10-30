import { createFilter } from '@zag-js/i18n-utils';
import { getGlobal, mountAll } from 'fluid-primitives';
import { Select } from 'fluid-primitives/select';

const filter = createFilter({ sensitivity: 'base', locale: getGlobal('locale') });

mountAll('ui:select', ({ props }) => {
    const select = new Select({
        ...props,
        onInputValueChange: ({ inputValue }) => {
            const source = select.getSourceCollection();
            const query = inputValue.trim();

            select.updateProps({
                collection: query
                    ? source.filter(itemString => filter.contains(itemString, query))
                    : source,
            });
        },
    });

    select.init();
    return select;
});
