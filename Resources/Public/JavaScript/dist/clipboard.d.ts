import { Component$1 as Component, Machine$1 as Machine } from "./index-u7KqKsiP.js";
import * as _zag_js_types5 from "@zag-js/types";
import * as clipboard from "@zag-js/clipboard";

//#region Resources/Private/Primitives/Clipboard/Clipboard.d.ts
declare class Clipboard extends Component<clipboard.Props, clipboard.Api> {
  static name: string;
  initMachine(props: clipboard.Props): Machine<any>;
  initApi(): clipboard.Api<_zag_js_types5.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Clipboard };