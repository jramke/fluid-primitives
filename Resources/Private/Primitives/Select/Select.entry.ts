import { mount } from '../../Client';
import { Select } from './Select';

(() => {
	mount('select', ({ props }) => {
		// @ts-expect-error
		const select = new Select(props);
		select.init();
		return select;
	});
})();
