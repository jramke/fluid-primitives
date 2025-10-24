import { initAllComponentInstances } from '../../Client';
import { Accordion } from './Accordion';

(() => {
	initAllComponentInstances('accordion', ({ props }) => {
		const accordion = new Accordion(props);
		accordion.init();
		return accordion;
	});
})();
