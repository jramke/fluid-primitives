import { Component$1 as Component, Machine$1 as Machine } from "./index-ir2YxHr8.js";
import * as _zag_js_types4 from "@zag-js/types";
import * as select from "@zag-js/select";

//#region Resources/Private/Primitives/Select/Select.d.ts
declare class Select extends Component<select.Props, select.Api> {
  name: string;
  initMachine(props: select.Props): Machine<any>;
  initApi(): select.Api<_zag_js_types4.PropTypes<{
    [x: string]: any;
  }>, any>;
  render: () => void;
}
//#endregion
export { Select };