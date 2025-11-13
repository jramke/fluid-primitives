import { Component$1 as Component, Machine$1 as Machine } from "./index-BRklKBux.js";
import { EventObject } from "@zag-js/core";
import { PropTypes } from "@zag-js/types";

//#region Resources/Private/Primitives/Form/src/form.registry.d.ts
type FormMachine = Machine<any>;
//#endregion
//#region Resources/Private/Primitives/Field/src/field.types.d.ts
interface FieldProps {
  id: string;
  name: string;
  invalid?: boolean;
  required?: boolean;
  disabled?: boolean;
  readOnly?: boolean;
}
interface FieldSchema {
  props: FieldProps;
  context: {
    invalid: boolean;
    required: boolean;
    disabled: boolean;
    readOnly: boolean;
    formMachine: FormMachine | null;
    describeIds: string | undefined;
  };
  state: 'ready';
  event: EventObject;
  action: string;
  effect: string;
}
interface FieldApi {
  getFormMachine(): FieldSchema['context']['formMachine'];
  invalid: boolean;
  errors: string[];
  name: string;
  getRootProps(): PropTypes['element'];
  getLabelProps(): PropTypes['label'];
  getControlProps(): PropTypes['element'];
  getErrorProps(): PropTypes['element'];
  getErrorText(): string | null;
}
//#endregion
//#region Resources/Private/Primitives/Field/Field.d.ts
declare class Field extends Component<FieldProps, FieldApi> {
  static name: string;
  private subscribedToForm;
  initMachine(props: FieldProps): Machine<FieldSchema>;
  initApi(): FieldApi;
  subscribeToFormMachine(): void;
  render(): void;
}
//#endregion
export { Field };