import { Component$1 as Component, Machine$1 as Machine } from "./index-w1HRp_7W.js";
import * as popover from "@zag-js/popover";

//#region Resources/Private/Primitives/Popover/Popover.d.ts
declare class Popover extends Component<popover.Props, popover.Api> {
  name: string;
  initMachine(props: popover.Props): Machine<any>;
  initApi(): any;
  render(): void;
}
//#endregion
export { Popover };