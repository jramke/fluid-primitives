import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-Dn0aMgBA.js";
import * as _zag_js_types8 from "@zag-js/types";
import * as numberInput from "@zag-js/number-input";

//#region Resources/Private/Primitives/NumberInput/NumberInput.d.ts
declare class NumberInput extends FieldAwareComponent<numberInput.Props, numberInput.Api> {
  static name: string;
  propsWithField(props: numberInput.Props, fieldMachine: FieldMachine): numberInput.Props;
  initMachine(props: numberInput.Props): Machine<any>;
  initApi(): numberInput.Api<_zag_js_types8.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { NumberInput };