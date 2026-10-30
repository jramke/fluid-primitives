# Changelog

What changed in each release of Fluid Primitives, newest first. Every release lists its breaking changes first, then the other changes under one heading per part of the library. The library follows [semantic versioning](https://semver.org), and the 1.0 betas may still change between releases.

## Unreleased

### Breaking changes

- **`ui:slotFilled`:** The ViewHelper is gone. Store the slot in a variable and test that: `<f:variable name="note" value="{f:slot() -> f:spaceless()}" />`, then `<f:if condition="{note}">`. This renders the slot once, where `{ui:slotFilled()}` followed by `<f:slot />` rendered it twice. Pass `name` to `f:slot` for a named slot. ([#101](https://github.com/jramke/fluid-primitives/pull/101))

## 1.0.0-beta.6

_2026-10-30_

### Breaking changes

- **Core:** `typo3fluid/fluid` has to be `^5.2`. ([#90](https://github.com/jramke/fluid-primitives/pull/90))
- **Core:** A template mistake throws an exception of `Jramke\FluidPrimitives\Exception` instead of a bare `\RuntimeException` or `\InvalidArgumentException`: a part outside its root is a `MissingContextException`, a missing prop a `MissingPropException`, a wrong value an `InvalidPropValueException`. Where you caught `\InvalidArgumentException` around a render, catch `ExceptionInterface` instead. ([#90](https://github.com/jramke/fluid-primitives/pull/90))
- **Field:** The `validationMode` of a `Field` is strict: `onBlur` validates on blur only and `onSubmit` on submit only, where both used to revalidate on every change once an error showed. The behaviour of the old `onBlur` default is the new `auto` mode, which is the default now. Set `validationMode="{f:constant(name: 'Jramke\FluidPrimitives\Enum\ValidationMode::Auto')}"` where you relied on an error clearing as the user fixes it in a field that sets `onBlur` or `onSubmit`. `validationMode` is no longer sent to the client unless a field sets one, so a field follows its form. ([#95](https://github.com/jramke/fluid-primitives/pull/95))
- **Form:** Every form control submits its name while empty, so Extbase can clear the property: an unchecked `Checkbox` or `Switch`, a `CheckboxGroup` with nothing checked, a `Select` and a `Combobox` or `DatePicker` without a value send `''` through an empty-value input, as `f:form.checkbox` does. A name that ends with `[]` is sent without the brackets (`tags`, not `tags[]`), which Extbase maps to an empty array or ObjectStorage instead of `['']`. An empty `Combobox` and `DatePicker` render no value input any more, so a `hiddenInput` part is there only while the control holds a value. The form values read an empty list as `[]`, and an unchecked box as `''`. ([#97](https://github.com/jramke/fluid-primitives/pull/97))
- **Select, Combobox, Slider, DatePicker:** `defaultValue` takes typed lists: `string|string[]` on `Select` and `Combobox` (an integer such as a uid throws now, it never selected an item), `float|integer|float[]|integer[]` on `Slider`, `DateTimeInterface|string|DateTimeInterface[]|string[]` on `DatePicker`. ([#97](https://github.com/jramke/fluid-primitives/pull/97))
- **Slider:** A range `Slider` submits under the `name` as you give it: end it with `[]` (`price[]`) for PHP to receive both values. The client used to add the `[]` after hydration only. ([#97](https://github.com/jramke/fluid-primitives/pull/97))

### General changes

- **Input, Textarea:** `ui:input.root` and `ui:textarea.root` take `maxLengthBehavior` (`Block`, `Warn` or `Invalid`). `Warn` and `Invalid` leave off the native `maxlength`, so a paste is not cut off: the word count switches to the new `wordCountOver` text ("Characters over the limit: 2"), and the root, the control and the count get `data-over-limit`, `data-at-limit` (exactly `maxLength` characters) and `data-max-length-behavior`, so `warn` can be styled apart from `invalid`. `Invalid` also makes a surrounding `Field` invalid with the `tooLong` validity, so `ui:field.errorText` with the `ValidityMatch::TooLong` match works and a form does not submit. The count is in UTF-16 units like the native `maxlength`, in the browser and in the server render, so an emoji counts twice and the count is never lower than `mb_strlen` or a `VARCHAR(n)` counts. The registry counter turns `text-destructive` over the limit and amber for `Warn` (run `ui:add` again to pick it up). ([#96](https://github.com/jramke/fluid-primitives/pull/96))
- **Field, Form:** `data-empty-value="<name>"` on an input tells `Field` and `Form` that it stands for an empty value: a field whose only input is such an input has no value, and one whose name ends with `[]` has an empty list. `extbase.post()` and `extbase.get()` send an empty list as `''`, where they used to leave it out and Extbase kept the old value. ([#97](https://github.com/jramke/fluid-primitives/pull/97))
- Every translation that a Zag.js machine wants as a function takes a text with `%placeholders%` in a template, and the function itself in a client script. `NumberInput` takes `valueText` (`%value%`), `Select` takes `itemAnnouncement` and `itemAnnouncementSelected` (`%label%`, `%value%`, with English and German defaults in the language file), and the `wordCount` and `wordCountOver` of `Input` and `Textarea` take a function of `{ count, max, over }`. `FileUpload` and `Clipboard` keep the functions a client script passes (`itemPreview`, `deleteFile`, `triggerLabel`) where they used to drop them. `fillPlaceholders()` and `resolveTranslations()` do this for a component of your own. ([#99](https://github.com/jramke/fluid-primitives/pull/99))
- **Tabs, NumberInput:** The registry `Tabs` trigger and the `NumberInput` increment and decrement triggers show a pointer cursor. The `NumberInput` triggers no longer set `pointer-events: none` when disabled, so repeated clicks on one do not select the text next to it. Run `ui:add` again to pick it up. ([#92](https://github.com/jramke/fluid-primitives/pull/92))
- **Popover, Tooltip:** The registry `Popover` and `Tooltip` scale and fade out of their trigger on every placement and skip the scale when a visitor prefers reduced motion. They used to slide down and blur on `transition-all`, which pointed the wrong way for content above its trigger. The `Popover` takes 250 ms instead of 150 ms. ([#94](https://github.com/jramke/fluid-primitives/pull/94))

### Client

- `destroyComponentsWithin()` forgets the hydration data of the components it destroys, so a later `mountAll()` does not mount a removed row again. ([#91](https://github.com/jramke/fluid-primitives/pull/91))

### Core

- Everything the library throws implements `Jramke\FluidPrimitives\Exception\ExceptionInterface`, so one `catch` takes it all. Fluid names the template file in the message of a ViewHelper that fails, and the exception of the library is the `getPrevious()` of the one you catch. ([#90](https://github.com/jramke/fluid-primitives/pull/90))
- `Typed::array()` narrows a value to an array with a default, like `Typed::string()` and the other `Typed` methods do for their types. ([#100](https://github.com/jramke/fluid-primitives/pull/100))

### Field

- A primitive can hand its `Field` a rule only it knows: `setConstraint()` on the field handle takes a function that returns a validity flag and a message, and a `FieldAwareComponent` returns one from `getFieldConstraint()`. The field runs it on every validation. ([#96](https://github.com/jramke/fluid-primitives/pull/96))

### FieldArray

- Removing a row of a `FieldArray` no longer breaks the components in the rows after it. A `Select`, `DatePicker` or `Popover` in a row that moved up kept reading and writing the ids it started with, so it stopped reacting, or opened the parts of a row you added later. A cloned row now keeps the root ids of its components until you remove it, and only the `data-value` of the row and the names of its fields change. ([#91](https://github.com/jramke/fluid-primitives/pull/91))
- The fields of a `FieldArray` outside a `Form` are renamed when you remove a row. Rows used to keep `people[1]` and `people[2]` after the first one was gone, and the next added row got `people[2]` too. ([#91](https://github.com/jramke/fluid-primitives/pull/91))

### Form

- `ui:form.root` takes `validationMode` (`auto`, `onBlur`, `onChange` or `onSubmit`) as the default for all its fields, and a field can still set its own. `inputDebounceMs` is a prop of `ui:form.root` as well, so you no longer need your own entry file to change how long fields wait for a pause in typing. ([#95](https://github.com/jramke/fluid-primitives/pull/95))

### NavigationMenu

- The registry `NavigationMenu` viewport no longer needs `tw-animate-css`: it opens and closes with the new `animate-viewport-in` and `animate-viewport-out` utilities. Copy their `--animate-viewport-*` theme entries from [Styling](/docs/core-concepts/styling) before you update the viewport. Projects that added the component earlier keep their copy. ([#98](https://github.com/jramke/fluid-primitives/pull/98))

### NumberInput

- `NumberInput` with `formatOptions` no longer formats the value while you type. Typing `1` into a currency input used to turn it into `$1.00` right away, now the value is formatted on blur and enter. The changes the input makes itself (arrow keys, the step buttons, the mouse wheel, the scrubber, formatting on blur) now fire a `change` event instead of an `input` event, once the input shows the new text, so a `Field` is told about them without the input reading its own old text back and a listener reads the new value. ([#93](https://github.com/jramke/fluid-primitives/pull/93))

### Textarea

- A `Textarea` default value that starts with a line break keeps it. The template wrote the value right behind the opening tag, where the HTML parser drops a first line break, so the value lost it and the count of the server-side render was one off. ([#96](https://github.com/jramke/fluid-primitives/pull/96))

### Toc

- `Toc` is a new primitive for a table of contents, built on the Zag.js `toc` machine. It watches the headings of the page with an `IntersectionObserver` and marks the items of the visible ones with `data-active` and their links with `aria-current="location"`. Pass the headings to `ui:toc.root` as `items`, a list of `Jramke\FluidPrimitives\Domain\Dto\TocItem` objects or of arrays, each with a `value` (the id of the heading) and a `depth`, and render a `ui:toc.item` for every one. The depth becomes the `--depth` CSS variable for indentation. `scrollEl` takes a CSS selector when the headings sit in a scroll container, `ui:toc.title` takes a `level`, and `ui:toc.indicator` follows the active items through `--top`, `--height` and `--left`. Nothing scrolls smoothly for a visitor who prefers reduced motion. ([#100](https://github.com/jramke/fluid-primitives/pull/100))

### `ui:emptyValueInput`

- Renders the input a control submits while it is empty. A context of your own takes `HasEmptyValueInputTrait` for the name of that input, and a `FieldAwareComponent` calls `syncEmptyValueInput()` to disable it while the control holds a value. ([#97](https://github.com/jramke/fluid-primitives/pull/97))

## 1.0.0-beta.5

_2026-10-26_

### Breaking changes

- **`ui:call`:** Escapes the text a method returns, like any other variable. Where you relied on HTML from it, use `f:format.raw`. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- **Core:** A `ListCollection` sends the client only the value, label, disabled flag and group of an item, at the paths its keys name. Every other key stays on the server and a template reads it from `item.original`. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- **`ui:attributes`:** An attribute name with a space, quote, `=`, `/`, `<`, `>` or `&` throws, and `href`, `src`, `action`, `formaction`, `poster`, `xlink:href`, `cite` and `data` leave out a `javascript:` or `vbscript:` URL. `skip` and `only` of `ui:attributes` match names without regard to case. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- **Collapsible:** `collapsedHeight` and `collapsedWidth` of a `Collapsible` have to be a CSS length or a `calc()`, `min()`, `max()` or `clamp()` expression, anything else throws. ([#87](https://github.com/jramke/fluid-primitives/pull/87))

### General changes

- The package declares `"sideEffects": false`. A component you import but never use, like `import { Select } from 'fluid-primitives/select'`, no longer ends up in your bundle. ([#88](https://github.com/jramke/fluid-primitives/pull/88))

### CLI

- `ui:add` reads the registry of the installed version from the Composer package. Nothing is fetched from GitHub any more, and a checkout of `main` gets its own templates. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- `ui:add` refuses a file name with a `.` or empty segment, does not write through a symlink out of the component folder, and reports a file it could not write instead of "Created". ([#87](https://github.com/jramke/fluid-primitives/pull/87))

### Core

- The hydration script keeps the whitespace of your data in production, so a label like `a  =  ( b )` is no longer saved as `a=(b)`. A site with a Content-Security-Policy gets a nonce for it. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- A form field named `__proto__[x]` is ignored, a quote in a root id cannot end the selector of a part, and a file name with `$&` is written as it is into ids and translations. `ui:ref(rootId:)` throws on a quote or backslash like a component's `rootId`. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- A `ListCollection` item with a number as its value or label is found by the client. Quoted values like `'1'` in an inline array no longer miss their item in `Select` and `Combobox`. The items no longer render a `data-item` attribute. ([`28e1504`](https://github.com/jramke/fluid-primitives/commit/28e1504))
- `asChild` keeps attribute values of the child element that contain `$` or `\`, like `title="Cost $10"`. A value such as `$0 onmouseover=...` no longer breaks out of its attribute and adds an event handler. ([#86](https://github.com/jramke/fluid-primitives/pull/86))

### Select

- A `Select` shows the selected label and the placeholder as text. An option like `<img src=x onerror=...>` no longer runs as markup, and `spreadProps` throws on a `children` prop. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
- The item text and item indicator of a `Select` render inside a `ui:template`, so a stencil can hold an item the client clones, as in `Combobox`. ([`f4285d4`](https://github.com/jramke/fluid-primitives/commit/f4285d4))

### `ui:attributes`

- An attribute you forward with `attributes="{ui:attributes(asArray: true)}"` or through `spreadProps` is escaped once. `Tom & "Jerry"` no longer arrives as `Tom &amp; &quot;Jerry&quot;`. ([#86](https://github.com/jramke/fluid-primitives/pull/86))

## 1.0.0-beta.4

_2026-10-23_

### Breaking changes

- **Form:** Forms send through `extbase.post`. The `onSubmit` callback of a form no longer receives a `post` helper, call `extbase.post(...)` instead. The form element no longer renders `data-field-name-prefix` and `data-object-name`. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- **Client:** `extbase.get()` and `extbase.post()` reject with an `ExtbaseValidationError` when the server answers with a JSON `422`. Every other status still resolves as a `Response`. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- **Core:** `BaseContext` is now `DefaultContext`, marked `final` and `@internal`. A context that declares `getGlobals()` fails to load, and `globals` is a reserved prop name. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- **Form:** A form rendered outside an Extbase request needs both `extensionName` and `pluginName`. Without them it throws instead of rendering fields without a prefix. ([#70](https://github.com/jramke/fluid-primitives/pull/70))
- **CLI:** `ui:add` writes all of a component or nothing. When one of its files already exists the command fails with a non-zero exit code, add `--force` to overwrite them all. ([#71](https://github.com/jramke/fluid-primitives/pull/71))
- **`ui:attributes`:** Never replaces an attribute the tag sets itself. A `hidden`, `style` or `role` passed by the caller no longer renders a second time. ([#85](https://github.com/jramke/fluid-primitives/pull/85))
- **Client:** A component that overrides `init()` has to return `this`. ([#82](https://github.com/jramke/fluid-primitives/pull/82))
- **Core:** Two hydrated roots of the same component with the same `rootId` throw. A root that is only server-rendered can repeat its `rootId`. ([#76](https://github.com/jramke/fluid-primitives/pull/76))

### General changes

- **DatePicker, NumberInput:** `DatePicker` and `NumberInput` fall back to the global locale. `NumberInput` now honours its `locale` prop. ([#73](https://github.com/jramke/fluid-primitives/pull/73))

### CLI

- The component templates that `ui:add` copies load their entry file through a `%componentPath%` token. The command warns when the `vite` Fluid namespace is not registered. ([#72](https://github.com/jramke/fluid-primitives/pull/72))
- The registry keys of multi-word components are camelCase (`checkboxGroup`). `ui:add checkbox-group` keeps working. ([#74](https://github.com/jramke/fluid-primitives/pull/74))

### Client

- `devWarn()` and `isDebug()` print warnings in development only, for example when `mount()` finds no hydration data. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- `init()` returns the instance, so a mount callback fits on one line. `AsyncList.init()` does too. ([#82](https://github.com/jramke/fluid-primitives/pull/82))

### Core

- A global context holds request-wide data for the server and the client. Read it in templates with `{context.globals.locale}` and on the client with `getGlobal()`. It ships the `locale` and `debug` globals, and a project can alias its own `GlobalContextInterface`. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- Clearer errors for `asChild` with several children, a part used outside its root, and a missing `extensionName` or `pluginName` on a form. ([#69](https://github.com/jramke/fluid-primitives/pull/69), [#70](https://github.com/jramke/fluid-primitives/pull/70))
- `ListCollection` accepts plain objects as items with any combination of `itemToValueKey`, `itemToStringKey` and `isItemDisabledKey`. ([#67](https://github.com/jramke/fluid-primitives/pull/67))

### Form

- `ui:add form` ships a working `onSubmit` that shows the `message` of the JSON response. The form maps the errors of a `422` to the form and to its fields. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- A form field name prefix is derived from the plugin that handles the action. It used to follow the plugin of the current request. ([#70](https://github.com/jramke/fluid-primitives/pull/70))

## 1.0.0-beta.3

_2026-10-14_

### CLI

- `ui:add` reads the component registry of a prerelease from its own tag. ([#66](https://github.com/jramke/fluid-primitives/pull/66))

## 1.0.0-beta.2

_2026-10-14_

### General changes

- **Toggle, ToggleGroup:** New `Toggle` and `ToggleGroup` primitives. ([`c94e22a`](https://github.com/jramke/fluid-primitives/commit/c94e22a), [`53c347e`](https://github.com/jramke/fluid-primitives/commit/53c347e))

### CLI

- `ui:add` reads the component registry from the tag of the installed version, so the files you copy match your library version. The registry is served from `raw.githubusercontent.com`. ([#65](https://github.com/jramke/fluid-primitives/pull/65))

## 1.0.0-beta.1

_2026-10-12_

First beta release of Fluid Primitives 1.0.

Install it with `composer require jramke/fluid-primitives:^1.0@beta` and `npm install fluid-primitives@beta`.
