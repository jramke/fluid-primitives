import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-5fDaNxFu.js";
import * as _zag_js_types4 from "@zag-js/types";
import * as select from "@zag-js/select";

//#region Resources/Private/Primitives/Select/Select.d.ts
declare class Select extends FieldAwareComponent<select.Props, select.Api> {
  static name: string;
  propsWithField(props: select.Props, fieldMachine: FieldMachine): select.Props;
  initMachine(props: select.Props): Machine<any>;
  initApi(): select.Api<_zag_js_types4.PropTypes<{
    [x: string]: any;
  }>, any>;
  render: () => void;
}
//#endregion
export { Select };