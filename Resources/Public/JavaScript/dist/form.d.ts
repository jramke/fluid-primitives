import { Component$1 as Component, Machine$1 as Machine } from "./index-Dn0aMgBA.js";
import { FieldMachine$1 as FieldMachine } from "./form.registry-B9EJCStd.js";
import { EventObject } from "@zag-js/core";
import { JSX, PropTypes } from "@zag-js/types";
import * as z from "zod";

//#region Resources/Private/Primitives/Form/src/form.types.d.ts
interface FieldError {
  messages: string[];
  value: FormDataEntryValue | FormDataEntryValue[] | null;
}
type FormErrors = Record<string, FieldError>;
type FormDirty = Record<string, boolean>;
type FormTouched = Record<string, boolean>;
/**
 * Error thrown by post() when server returns 422 validation errors.
 * The machine catches this and transitions to 'invalid' state.
 */
declare class ValidationError extends Error {
  errors: FormErrors;
  constructor(errors: FormErrors);
}
type ZodFormSchema = z.ZodObject | undefined;
interface FormProps {
  id: string;
  schema?: ZodFormSchema;
  objectName?: string;
  inputDebounceMs?: number;
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
    touched: FormTouched;
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
  getTouched(): FormTouched;
  _userRenderFn: FormProps['render'];
  getAllFields(): Map<string, FieldMachine>;
  getField(name: string): FieldMachine | undefined;
  getFormEl(): HTMLFormElement | null;
  getAction(): string;
  reset(): void;
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
export { Form, ValidationError };