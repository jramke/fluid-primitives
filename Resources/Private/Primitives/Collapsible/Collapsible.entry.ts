import { mount } from '../../Client';
import { Collapsible } from './Collapsible';

(() => {
	mount('collapsible', ({ props }) => {
		const collapsible = new Collapsible(props);
		collapsible.init();
		return collapsible;
	});
})();
