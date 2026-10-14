import { mountAll } from 'fluid-primitives';
import { DatePicker } from 'fluid-primitives/date-picker';

mountAll('ui:datePicker', ({ props }) => {
    const datePicker = new DatePicker(props);
    datePicker.init();
    return datePicker;
});
