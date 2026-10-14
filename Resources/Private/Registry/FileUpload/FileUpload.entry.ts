import { mountAll } from 'fluid-primitives';
import { FileUpload } from 'fluid-primitives/file-upload';

mountAll('ui:fileUpload', ({ props }) => {
    const fileUpload = new FileUpload(props);
    fileUpload.init();
    return fileUpload;
});
