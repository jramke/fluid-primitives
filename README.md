![The headless component library for TYPO3 Fluid](https://raw.githubusercontent.com/jramke/fluid-primitives.com/refs/heads/main/packages/docs/Resources/Public/Images/og-image.png)

# Fluid Primitives

Unstyled, flexible and accessible UI Primitives that provide a foundation for building your own component library in Fluid.

Fluid Primitives brings modern component patterns to TYPO3. Build accessible, composable UI components with the same developer experience you'd expect from React libraries like Radix or Base UI - but for Fluid templates.

Full documentation can be found at [fluid-primitives.com](https://fluid-primitives.com).

## What You Get

**Accessible by default.** Every interactive component handles keyboard navigation, focus management, and ARIA attributes automatically via [Zag.js](https://zagjs.com/) state machines.

**Composable API.** No more prop drilling. Build complex UIs by composing small, focused parts that work together.

**Unstyled.** Zero design opinions. Use Tailwind, vanilla CSS, or any styling approach. You control every pixel.

**Server-rendered.** Components render on the server with PHP/Fluid, then hydrate on the client. No layout shift, great for SEO.

## Quick Example

A tooltip with full accessibility support in just a few lines:

```html
<ui:tooltip.root>
    <ui:tooltip.trigger>Hover me</ui:tooltip.trigger>
    <ui:tooltip.content>Tooltip content here</ui:tooltip.content>
</ui:tooltip.root>
```

That's it. Keyboard support, focus handling, proper ARIA attributes - all handled.

## Why This Exists

TYPO3 Fluid lacked an elegant solution for building robust, interactive components. The typical approach leads to bloated templates with complex conditional logic, poor accessibility, and custom JavaScript that's hard to maintain.

Fluid Primitives solves this by bringing proven patterns from the modern frontend ecosystem to TYPO3, while respecting its server-first architecture.

## Acknowledgments

Built on the shoulders of giants:

- [Zag.js](https://zagjs.com/) - The state machine foundation
- [Radix UI](https://www.radix-ui.com/primitives) - API design inspiration
- [Base UI](https://base-ui.com/) - Component behavior patterns
- [Ark UI](https://ark-ui.com/) - Zag.js integration patterns

## Documentation

The full documentation can be found at [fluid-primitives.com](https://fluid-primitives.com).

## Development

See [github.com/jramke/fluid-primitives.com](https://github.com/jramke/fluid-primitives.com) monorepo for the development setup.
