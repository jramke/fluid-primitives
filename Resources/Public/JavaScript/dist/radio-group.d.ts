import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-Dn0aMgBA.js";
import * as _zag_js_types4 from "@zag-js/types";
import * as radioGroup from "@zag-js/radio-group";

//#region Resources/Private/Primitives/RadioGroup/RadioGroup.d.ts
declare class RadioGroup extends FieldAwareComponent<radioGroup.Props, radioGroup.Api> {
  static name: string;
  propsWithField(props: radioGroup.Props, fieldMachine: FieldMachine): radioGroup.Props;
  initMachine(props: radioGroup.Props): Machine<any>;
  initApi(): radioGroup.Api<_zag_js_types4.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { RadioGroup };