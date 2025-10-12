import { initAllComponentInstances } from '../../Client';
import { Collapsible } from './Collapsible';

(() => {
	initAllComponentInstances('collapsible', ({ props }) => {
		const collapsible = new Collapsible(props);
		collapsible.init();
		return collapsible;
	});
})();
