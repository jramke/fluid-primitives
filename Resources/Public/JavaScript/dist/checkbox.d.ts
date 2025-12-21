import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-iJSgk-K3.js";
import * as _zag_js_types2 from "@zag-js/types";
import * as checkbox from "@zag-js/checkbox";

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