import { Component$1 as Component, Machine$1 as Machine } from "./index-w1HRp_7W.js";
import * as popover from "@zag-js/popover";

//#region Resources/Private/Primitives/Popover/Popover.d.ts
declare class Popover extends Component<popover.Props, popover.Api> {
  static name: string;
  initMachine(props: popover.Props): Machine<any>;
  initApi(): popover.Api<{
    button: {
      [x: string]: any;
    };
    label: {
      [x: string]: any;
    };
    input: {
      [x: string]: any;
    };
    textarea: {
      [x: string]: any;
    };
    img: {
      [x: string]: any;
    };
    output: {
      [x: string]: any;
    };
    element: {
      [x: string]: any;
    };
    select: {
      [x: string]: any;
    };
    rect: {
      [x: string]: any;
    };
    style: {
      [x: string]: any;
    };
    circle: {
      [x: string]: any;
    };
    svg: {
      [x: string]: any;
    };
    path: {
      [x: string]: any;
    };
  }>;
  render(): void;
}
//#endregion
export { Popover };