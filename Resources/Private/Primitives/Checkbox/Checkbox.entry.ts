import { initAllComponentInstances } from '../../Client';
import { Checkbox } from './Checkbox';

(() => {
	initAllComponentInstances('checkbox', ({ props }) => {
		const checkbox = new Checkbox(props);
		checkbox.init();
		return checkbox;
	});
})();
