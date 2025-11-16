import { Component$1 as Component, Machine$1 as Machine } from "./index-C2xweJ6P.js";
import { EventObject } from "@zag-js/core";
import { PropTypes } from "@zag-js/types";
import * as v from "valibot";

//#region Resources/Private/Primitives/Form/src/form.types.d.ts
type FormValues = Record<string, unknown>;
type FormErrors = Record<string, string[]>;
type FormDirty = Record<string, boolean>;
type ValibotFormSchema = v.GenericSchema;
interface FormProps {
  id: string;
  schema: ValibotFormSchema;
  validateOnChange?: boolean;
  onSubmit?: (values: FormValues) => Promise<boolean> | boolean;
}
interface FormSchema {
  props: FormProps;
  context: {
    values: FormValues;
    initialValues: FormValues;
    errors: FormErrors;
    dirty: FormDirty;
  };
  state: 'validating' | 'ready' | 'submitting' | 'success' | 'error';
  event: EventObject;
  action: string;
  effect: string;
}
interface FormApi {
  isSubmitting: boolean;
  getFormProps(): PropTypes['element'];
  getValues(): FormValues;
  getErrors(): FormErrors;
  getDirty(): FormDirty;
  getFieldState(name: string): {
    value: unknown;
    errors: string[];
    dirty: boolean;
    invalid: boolean;
  };
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