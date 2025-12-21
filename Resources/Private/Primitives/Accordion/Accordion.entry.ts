import { mount } from '../../Client';
import { Accordion } from './Accordion';

(() => {
	mount('accordion', ({ props }) => {
		const accordion = new Accordion(props);
		accordion.init();
		return accordion;
	});
})();
