import { mount } from '../../Client';
import { RadioGroup } from './RadioGroup';

(() => {
	mount('radio-group', ({ props }) => {
		const radioGroup = new RadioGroup(props);
		radioGroup.init();
		return radioGroup;
	});
})();
