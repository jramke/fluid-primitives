import { Component$1 as Component, Machine$1 as Machine } from "./index-w1HRp_7W.js";
import * as _zag_js_types4 from "@zag-js/types";
import * as tooltip from "@zag-js/tooltip";

//#region Resources/Private/Primitives/Tooltip/Tooltip.d.ts
declare class Tooltip extends Component<tooltip.Props, tooltip.Api> {
  static name: string;
  initMachine(props: tooltip.Props): Machine<any>;
  initApi(): tooltip.Api<_zag_js_types4.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Tooltip };