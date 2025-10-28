import { Component$1 as Component, Machine$1 as Machine } from "../index-Oxoe0m6L.js";
import * as tabs from "@zag-js/tabs";

//#region Resources/Private/Primitives/Tabs/Tabs.d.ts
declare class Tabs extends Component<tabs.Props, tabs.Api> {
  name: string;
  initMachine(props: tabs.Props): Machine<any>;
  initApi(): tabs.Api<{
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
  render: () => void;
}
//#endregion
export { Tabs };