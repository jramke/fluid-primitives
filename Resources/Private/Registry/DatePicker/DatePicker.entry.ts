import { mountAll } from 'fluid-primitives';
import { DatePicker } from 'fluid-primitives/date-picker';

mountAll('ui:datePicker', ({ props }) => new DatePicker(props).init());
