import { mountAll } from 'fluid-primitives';
import { FileUpload } from 'fluid-primitives/file-upload';

mountAll('ui:fileUpload', ({ props }) => new FileUpload(props).init());
