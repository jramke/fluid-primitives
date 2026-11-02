import { mountAll } from 'fluid-primitives';
import { PasswordInput } from 'fluid-primitives/password-input';

mountAll('ui:passwordInput', ({ props }) => new PasswordInput(props).init());
