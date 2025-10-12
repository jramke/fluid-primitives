import { initAllComponentInstances } from '../../Client';
import { Select } from './Select';

(() => {
	initAllComponentInstances('select', ({ props }) => {
		// @ts-expect-error
		const select = new Select(props);
		select.init();
		return select;
	});
})();
