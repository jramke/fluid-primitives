import { Component$1 as Component, Machine$1 as Machine } from "./index-BRklKBux.js";
import * as _zag_js_types2 from "@zag-js/types";
import * as checkbox from "@zag-js/checkbox";

//#region Resources/Private/Primitives/Field/src/field.registry.d.ts
type FieldMachine = Machine<any>;
//#endregion
//#region Resources/Private/Client/src/lib/field-aware-component.d.ts
declare abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
  protected subscribedToField: boolean;
  protected fieldMachine: FieldMachine | undefined;
  protected closestField: Element | null;
  protected abstract propsWithField(props: Props, fieldMachine: FieldMachine): Props;
  protected getClosestField(): Element | null;
  protected withFieldProps(props: Props): Props;
  subscribeToFieldService(): void;
}
//#endregion
//#region Resources/Private/Primitives/Checkbox/Checkbox.d.ts
declare class Checkbox extends FieldAwareComponent<checkbox.Props, checkbox.Api> {
  static name: string;
  propsWithField(props: checkbox.Props, fieldMachine: FieldMachine): checkbox.Props;
  initMachine(props: checkbox.Props): Machine<any>;
  initApi(): checkbox.Api<_zag_js_types2.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Checkbox };