<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentRefUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Marks a part of a component for JavaScript interaction or styling.
 *
 * It renders a single `data-<component>-<part>="<rootId>"` attribute - the convention Zag.js uses for
 * its own parts - so the client finds the element with `query('<part>')` and CSS can target it with
 * `[data-<component>-<part>]`. No `id` is generated: Zag adds the ids it needs for ARIA links itself
 * when the component hydrates.
 *
 * ## Example
 * ```html
 * <div {ui:ref(name: 'button')}>Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div data-my-component-button="«uniqueRootId»">Click me</div>
 * ```
 *
 * For multi-instance parts (e.g. accordion items, tab panels) pass a `value:` discriminator, which
 * additionally renders `data-value`:
 * ```html
 * <div {ui:ref(name: 'item', value: value)}>...</div>
 * ```
 *
 * A part without a `value:` may legitimately appear more than once within one component instance
 * (e.g. two close buttons of a dialog) - read them with `queryAll()` on the client.
 *
 * Ids are only rendered where you declare them: pass `ids` on the component's root
 * (`ids="{content: 'my-content'}"`, the same override Zag uses - for the primitives and your own
 * components alike) and the ref'd part with that name renders that `id`, while the client uses the
 * same one. Never write an `id` attribute on an element that carries `ui:ref` yourself - the client
 * doesn't know about it and Zag replaces it on hydration. A part rendered with a `value:` never
 * gets an id from `ids`.
 *
 * You can also pass additional data attributes:
 * ```html
 * <div {ui:ref(name: 'button', data: { action: 'submit' })}>Click me</div>
 * ```
 * This will generate:
 * ```html
 * <div data-my-component-button="..." data-action="submit">Click me</div>
 * ```
 *
 * `rootId` attaches the ref to another instance of the same component, by its root id, instead of the
 * ambient one - for a part that belongs to a different instance's scope than the template it's
 * rendered in, like a submenu's trigger item, which sits in its parent menu's content but is the
 * submenu's own anchor. The ambient component's `ids` don't apply to it:
 * ```html
 * <div {ui:ref(name: 'triggerItem', rootId: childId)}>Share</div>
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
            'string|int|float|BackedEnum|UnitEnum|null|array',
            'Optional discriminator for multi-instance parts (e.g. accordion items, tab triggers, slider thumbs).',
            false,
            null,
        );
        $this->registerArgument(
            'rootId',
            'string',
            'Root id of another instance of the same component to attach this ref to instead of the ambient one, e.g. a submenu\'s trigger item rendered inside its parent menu\'s content. The ambient component\'s `ids` do not apply.',
            false,
            '',
        );
        $this->registerArgument(
            'context',
            'string',
            'camelCase base name of an ancestor component to attach this ref to explicitly (e.g. "fileUpload"), for hand-authored elements living in another component\'s slot content rather than a component\'s own template body. When omitted, uses whichever component is already ambiently active (the normal case for a component\'s own template).',
            false,
            '',
        );
    }

    public function render(): string|array
    {
        [$componentName, $rootId, $idsArray] = $this->resolveComponentIdentity();

        $part = (string)$this->arguments['name'];
        // Deliberately left as the full declared union (string|int|float|BackedEnum|UnitEnum|null|array)
        // rather than narrowed here - the array case is still meaningful for the (string) cast below, and
        // Typed::stringOrNull() would silently discard it.
        // @mago-expect analysis:mixed-assignment
        $value = EnumUtility::normalize($this->arguments['value']);

        $additionalDataRaw = Typed::arrayOrNull($this->arguments['data']) ?? [];
        $additionalData = $additionalDataRaw;
        if ($additionalData !== []) {
            $additionalData = array_combine(
                array_map(static fn($key) => "data-{$key}", array_keys($additionalDataRaw)),
                array_values($additionalDataRaw),
            );
        }

        $baseAttributes = [ComponentRefUtility::getAttributeName($componentName, $part) => $rootId];

        if ($value !== null) {
            $baseAttributes['data-value'] = (string)$value;
        }

        $explicitId = $value === null ? $idsArray[$part] ?? '' : '';
        if ($explicitId !== '') {
            $baseAttributes = ['id' => $explicitId, ...$baseAttributes];
        }

        ReferencedRootRegistry::mark($componentName, $rootId);

        $attributes = new TagAttributes(array_merge($baseAttributes, $additionalData));

        if (Typed::bool($this->arguments['asArray'])) {
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

        // $ids stays mixed here by design - normalizeIdsArray() below is the one place that
        // validates/narrows it, and pre-narrowing it here would just duplicate that check.
        // @mago-expect analysis:mixed-assignment
        [$componentName, $rootId, $ids] = $explicitContextName !== ''
            ? $this->resolveExplicitContext($explicitContextName)
            : $this->resolveAmbientContext();

        $explicitRootId = (string)($this->arguments['rootId'] ?? '');
        if ($explicitRootId !== '') {
            return [$componentName, $explicitRootId, []];
        }

        if ($rootId === '' || $rootId === '0') {
            throw new \RuntimeException('No rootId found for component ' . $componentName . '.', 1756025267);
        }

        return [$componentName, $rootId, $this->normalizeIdsArray($ids)];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeIdsArray(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $result = [];
        foreach (array_map(Typed::string(...), $ids) as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function resolveExplicitContext(string $explicitContextName): array
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Ref ViewHelper is missing its rendering context.',
            1_788_100_008,
        );
        // ContextService is keyed by the component's canonical camelCase base name, so no
        // conversion happens on the lookup itself. $componentName stays kebab here - it's used
        // below for the ref attribute name/registry key, which must match everywhere else.
        $componentName = ComponentNameUtility::camelCaseToLowerCaseDashed($explicitContextName);
        $context = ContextService::requireFromRenderingContext($renderingContext, $explicitContextName, 'ui:ref');

        return [$componentName, (string)($context->get('rootId') ?? ''), $context->get('ids') ?? []];
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function resolveAmbientContext(): array
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Ref ViewHelper is missing its rendering context.',
            1_788_100_009,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new \RuntimeException('The ref ViewHelper can only be used inside a component context.', 1698255600);
        }

        return [
            ComponentNameUtility::getClientBaseNameFromContext($renderingContext),
            ComponentUtility::getRootIdFromContext($renderingContext),
            $renderingContext->getVariableProvider()->getByPath('context.ids') ?? [],
        ];
    }
}
