import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-u7KqKsiP.js";
import * as _zag_js_collection0 from "@zag-js/collection";
import * as _zag_js_types7 from "@zag-js/types";
import * as select from "@zag-js/select";

//#region Resources/Private/Primitives/Select/Select.d.ts
declare class Select extends FieldAwareComponent<select.Props, select.Api> {
  static name: string;
  propsWithField(props: select.Props, fieldMachine: FieldMachine): select.Props;
  transformProps(props: select.Props): {
    collection: _zag_js_collection0.ListCollection<any>;
    ids?: Partial<{
      root: string;
      content: string;
      control: string;
      trigger: string;
      clearTrigger: string;
      label: string;
      hiddenSelect: string;
      positioner: string;
      item: (id: string | number) => string;
      itemGroup: (id: string | number) => string;
      itemGroupLabel: (id: string | number) => string;
    }> | undefined;
    name?: string | undefined;
    form?: string | undefined;
    disabled?: boolean | undefined;
    invalid?: boolean | undefined;
    readOnly?: boolean | undefined;
    required?: boolean | undefined;
    closeOnSelect?: boolean | undefined;
    onSelect?: ((details: select.SelectionDetails) => void) | undefined;
    onHighlightChange?: ((details: select.HighlightChangeDetails<any>) => void) | undefined;
    onValueChange?: ((details: select.ValueChangeDetails<any>) => void) | undefined;
    onOpenChange?: ((details: select.OpenChangeDetails) => void) | undefined;
    positioning?: select.PositioningOptions | undefined;
    value?: string[] | undefined;
    defaultValue?: string[] | undefined;
    highlightedValue?: string | null | undefined;
    defaultHighlightedValue?: string | null | undefined;
    loopFocus?: boolean | undefined;
    multiple?: boolean | undefined;
    open?: boolean | undefined;
    defaultOpen?: boolean | undefined;
    scrollToIndexFn?: ((details: select.ScrollToIndexDetails) => void) | undefined;
    composite?: boolean | undefined;
    deselectable?: boolean | undefined;
    dir?: "ltr" | "rtl" | undefined;
    id: string;
    getRootNode?: (() => ShadowRoot | Document | Node) | undefined;
    onPointerDownOutside?: ((event: select.PointerDownOutsideEvent) => void) | undefined;
    onFocusOutside?: ((event: select.FocusOutsideEvent) => void) | undefined;
    onInteractOutside?: ((event: select.InteractOutsideEvent) => void) | undefined;
  };
  initMachine(props: select.Props): Machine<any>;
  initApi(): select.Api<_zag_js_types7.PropTypes<{
    [x: string]: any;
  }>, any>;
  render: () => void;
}
//#endregion
export { Select };