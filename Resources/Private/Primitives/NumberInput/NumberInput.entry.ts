import { mount } from '../../Client';
import { NumberInput } from './NumberInput';

(() => {
	mount('number-input', ({ props }) => {
		const numberInput = new NumberInput(props);
		numberInput.init();
		return numberInput;
	});
})();
