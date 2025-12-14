import { Component$1 as Component, Machine$1 as Machine } from "./index-6fHSGVtd.js";
import { FieldMachine$1 as FieldMachine } from "./form.registry-Dm-lqKoD.js";
import { EventObject } from "@zag-js/core";
import { JSX, PropTypes } from "@zag-js/types";
import * as v from "valibot";

//#region Resources/Private/Primitives/Form/src/form.types.d.ts
type FormErrors = Record<string, string[]>;
type FormDirty = Record<string, boolean>;
type ValibotFormSchema = v.ObjectSchema<v.ObjectEntries, v.ErrorMessage<v.ObjectIssue> | undefined>;
interface FormProps {
  id: string;
  schema?: ValibotFormSchema;
  reactiveFields?: string[];
  objectName?: string;
  onSubmit?: ({
    formData,
    api,
    event,
    post
  }: {
    formData: FormData;
    api: FormApi;
    event: JSX.FormEvent<HTMLElement>;
    post: (url: string, data: FormData) => Promise<Response>;
  }) => Promise<boolean> | boolean;
  render?: (form: Form) => void;
}
interface FormSchema {
  props: FormProps;
  context: {
    values: FormData;
    initialValues: FormData;
    errors: FormErrors;
    dirty: FormDirty;
  };
  state: 'invalid' | 'ready' | 'submitting' | 'success' | 'error';
  event: EventObject;
  action: string;
  effect: string;
}
interface FormApi {
  isSubmitting: boolean;
  isDirty: boolean;
  isInvalid: boolean;
  isSuccessful: boolean;
  isError: boolean;
  getFormProps(): PropTypes['element'];
  getValues(): FormData;
  getErrors(): FormErrors;
  getDirty(): FormDirty;
  userRenderFn: FormProps['render'];
  getFields(): Map<string, FieldMachine>;
  getFormEl(): HTMLFormElement | null;
  getAction(): string;
}
//#endregion
//#region Resources/Private/Primitives/Form/Form.d.ts
declare class Form extends Component<FormProps, FormApi> {
  static name: string;
  initMachine(props: FormProps): Machine<FormSchema>;
  initApi(): FormApi;
  render(): void;
}
//#endregion
export { Form };