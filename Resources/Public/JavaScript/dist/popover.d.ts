import { Component$1 as Component, Machine$1 as Machine } from "./index-Dn0aMgBA.js";
import * as _zag_js_types0 from "@zag-js/types";
import * as popover from "@zag-js/popover";

//#region Resources/Private/Primitives/Popover/Popover.d.ts
declare class Popover extends Component<popover.Props, popover.Api> {
  static name: string;
  initMachine(props: popover.Props): Machine<any>;
  initApi(): popover.Api<_zag_js_types0.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Popover };