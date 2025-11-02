import { Component$1 as Component, Machine$1 as Machine } from "./index-BQR4r1rn.js";
import * as tooltip from "@zag-js/tooltip";

//#region Resources/Private/Primitives/Tooltip/Tooltip.d.ts
declare class Tooltip extends Component<tooltip.Props, tooltip.Api> {
  name: string;
  initMachine(props: tooltip.Props): Machine<any>;
  initApi(): tooltip.Api<{
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
export { Tooltip };