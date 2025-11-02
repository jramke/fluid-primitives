import { Component$1 as Component, Machine$1 as Machine } from "./index-u51T4Hsb.js";
import * as _zag_js_types0 from "@zag-js/types";
import * as checkbox from "@zag-js/checkbox";

//#region Resources/Private/Primitives/Checkbox/Checkbox.d.ts
declare class Checkbox extends Component<checkbox.Props, checkbox.Api> {
  name: string;
  initMachine(props: checkbox.Props): Machine<any>;
  initApi(): checkbox.Api<_zag_js_types0.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Checkbox };