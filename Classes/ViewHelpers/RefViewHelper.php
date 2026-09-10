<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a reference to a part of a component.
 *
 * This is used to mark parts of a component for JavaScript interaction or styling.
 * It generates the element `id` (using a deterministic formula based on component name, root ID and part name)
 * along with `data-scope` and `data-part` attributes.
 *
 * ## Example
 * ```html
 * <div {ui:ref(name: 'button')}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div id="my-component:«uniqueRootId»:button" data-scope="my-component" data-part="button">Click me</div>
 * ```
 *
 * For multi-instance parts (e.g. accordion items, tab panels) pass a `value:` discriminator:
 * ```html
 * <div {ui:ref(name: 'item', value: value)}">...</div>
 * ```
 *
 * You can also pass additional data attributes:
 * ```html
 * <div {ui:ref(name: 'button', data: { action: 'submit' })}">Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div id="..." data-scope="my-component" data-part="button" data-action="submit">Click me</div>
 * ```
 *
 * Use `withId: false` to suppress the `id` attribute (e.g. for parts that have no unique
 * discriminator and would produce duplicate IDs):
 * ```html
 * <div {ui:ref(name: 'item-group-label', withId: false)}>...</div>
 * ```
 *
 * A component's slot content (the markup a consumer writes between its opening/closing tags) is
 * always evaluated against the *calling* rendering context, not the component's own internal one -
 * so a bare `ui:ref` written directly inside such slot content doesn't, by default, know which
 * component (or rootId) it belongs to, and throws. Pass `context` to attach it explicitly to a
 * named ancestor component instead (resolved the same way `ui:template`'s own `context` argument
 * is - it threads correctly through slot-content nesting, unlike the ambient `component`/`context`
 * variables this ViewHelper otherwise reads):
 * ```html
 * <ui:combobox.root>
 *   <ui:combobox.content>
 *     <div>
 *       <span {ui:ref(name: 'statusText', context: 'combobox')}>Loading…</span>
 *     </div>
 *   </ui:combobox.content>
 * </ui:combobox.root>
 * ```
 * Use `ui:template` instead when the content's real data doesn't exist yet at server-render time
 * and needs cloning client-side per instance (e.g. async search results) - `context` here is for
 * hand-authored elements that render immediately, once, and never get cloned.
 */
class RefViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Name of the ref', true);
        $this->registerArgument(
            'asArray',
            'boolean',
            'If true, the ref will be rendered as an array instead of a string of data-attributes',
            false,
            false,
        );
        $this->registerArgument(
            'data',
            'array',
            'Additional data attributes to include in the ref. Associative array with key-value pairs. Each key is prefixed with "data-".',
            false,
            [],
        );
        $this->registerArgument(
            'value',
            'string|BackedEnum|UnitEnum|null|array',
            'Optional discriminator for multi-instance parts (e.g. accordion items, tab triggers).',
            false,
            null,
        );
        $this->registerArgument(
            'withId',
            'boolean',
            'Whether to emit the id attribute. Set to false for parts that have no unique discriminator and would produce duplicate IDs.',
            false,
            true,
        );
        $this->registerArgument(
            'context',
            'string',
            'Base name of an ancestor component to attach this ref to explicitly (e.g. "combobox"), for hand-authored elements living in another component\'s slot content rather than a component\'s own template body. When omitted, uses whichever component is already ambiently active (the normal case for a component\'s own template).',
            false,
            '',
        );
    }

    public function render(): mixed
    {
        [$componentName, $rootId, $idsArray] = $this->resolveComponentIdentity();

        $part = (string)$this->arguments['name'];
        $value = EnumUtility::normalize($this->arguments['value']);

        $additionalData = $this->arguments['data'];
        if ($additionalData !== []) {
            $additionalData = array_combine(
                array_map(static fn($key) => "data-{$key}", array_keys($this->arguments['data'])),
                array_values($this->arguments['data']),
            );
        }

        $baseAttributes = [
            'data-scope' => $componentName,
            'data-part' => ComponentUtility::camelCaseToLowerCaseDashed($part),
        ];

        if ($value !== null) {
            $baseAttributes['data-value'] = (string)$value;
        }

        if ($this->arguments['withId']) {
            $id = ComponentUtility::generatePartId($componentName, $rootId, $part, $value, $idsArray);
            $baseAttributes = array_merge(['id' => $id], $baseAttributes);
        }

        $attributes = new TagAttributes(array_merge($baseAttributes, $additionalData));

        if ($this->arguments['asArray']) {
            return $attributes->renderAsArray();
        }

        return (string)$attributes;
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, string>} [componentName, rootId, idsArray]
     */
    private function resolveComponentIdentity(): array
    {
        $explicitContextName = (string)($this->arguments['context'] ?? '');

        [$componentName, $rootId, $ids] = $explicitContextName !== ''
            ? $this->resolveExplicitContext($explicitContextName)
            : $this->resolveAmbientContext();

        if ($rootId === '' || $rootId === '0') {
            throw new \RuntimeException('No rootId found for component ' . $componentName . '.', 1756025267);
        }

        return [$componentName, $rootId, is_array($ids) ? $ids : []];
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function resolveExplicitContext(string $explicitContextName): array
    {
        $context = ContextService::requireFromRenderingContext($this->renderingContext, $explicitContextName, 'ui:ref');

        return [$explicitContextName, (string)($context->get('rootId') ?? ''), $context->get('ids') ?? []];
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function resolveAmbientContext(): array
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException('The ref ViewHelper can only be used inside a component context.', 1698255600);
        }

        return [
            ComponentUtility::getComponentBaseNameFromContext($this->renderingContext),
            ComponentUtility::getRootIdFromContext($this->renderingContext),
            $this->renderingContext->getVariableProvider()->getByPath('context.ids') ?? [],
        ];
    }
}
