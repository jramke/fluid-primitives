import { mount } from '../../Client';
import { Checkbox } from './Checkbox';

(() => {
	mount('checkbox', ({ props }) => {
		const checkbox = new Checkbox(props);
		checkbox.init();
		return checkbox;
	});
})();
