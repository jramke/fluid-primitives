import { Component$1 as Component, Machine$1 as Machine } from "./index-BQR4r1rn.js";
import * as collapsible from "@zag-js/collapsible";

//#region Resources/Private/Primitives/Collapsible/Collapsible.d.ts
declare class Collapsible extends Component<collapsible.Props, collapsible.Api> {
  name: string;
  initMachine(props: collapsible.Props): Machine<any>;
  initApi(): collapsible.Api<{
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
export { Collapsible };