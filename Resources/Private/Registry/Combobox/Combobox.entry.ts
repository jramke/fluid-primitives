import type { InputValueChangeDetails } from '@zag-js/combobox';
import { createFilter } from '@zag-js/i18n-utils';
import { getGlobal, mountAll } from 'fluid-primitives';
import { Combobox } from 'fluid-primitives/combobox';

const filter = createFilter({ sensitivity: 'base', locale: getGlobal('locale') });

mountAll('ui:combobox', ({ props }) => {
    const combobox = new Combobox({
        ...props,
        onInputValueChange: (details: InputValueChangeDetails) => {
            const source = combobox.getSourceCollection();

            if (details.reason !== 'input-change') {
                combobox.updateProps({ collection: source });
                return;
            }

            const query = details.inputValue.trim();
            combobox.updateProps({
                collection: query
                    ? source.filter(itemString => filter.contains(itemString, query))
                    : source,
            });
        },
    });

    combobox.init();
    return combobox;
});
