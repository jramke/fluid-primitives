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
 *
 * Marks a part of a component so that JavaScript and CSS can find it.
 *
 * ```html
 * <button {ui:ref(name: 'addToBasket')}>Add to basket</button>
 * ```
 *
 * This renders:
 *
 * ```html
 * <button data-product-card-add-to-basket="«fq7Zr2LpA1»">Add to basket</button>
 * ```
 *
 * The attribute is `data-<component>-<part>`, the convention Zag.js uses for its own parts, and its value is the root id of the component. The client finds the part with `hydrator.query('addToBasket')`, CSS with `[data-product-card-add-to-basket]`. Zag.js adds the ids the ARIA links need when the component hydrates.
 *
 * ## Mark one of many parts
 *
 * Pass a `value` for parts that repeat, such as accordion items or tab panels. It also renders `data-value`:
 *
 * ```html
 * <div {ui:ref(name: 'item', value: item.value)}>...</div>
 * ```
 *
 * A part without a `value` can appear several times in one instance, for example two close buttons of a dialog. Read them with `hydrator.queryAll()`.
 *
 * ## Add data attributes
 *
 * `data` renders its keys as `data-` attributes next to the part attribute:
 *
 * ```html
 * <button {ui:ref(name: 'addToBasket', data: {sku: product.sku})}>Add to basket</button>
 * ```
 *
 * ## Give a part an id
 *
 * Pass `ids` on the root of the component, the same prop Zag.js uses. The part with that name renders the id and the client uses the same one:
 *
 * ```html
 * <ui:dialog.root ids="{content: 'cookie-settings'}">...</ui:dialog.root>
 * ```
 *
 * Set ids through `ids`. An `id` attribute written on the element is unknown to the client and Zag.js replaces it when the component hydrates. A part with a `value` takes no id from `ids`.
 *
 * ## Attach a part to another instance
 *
 * A part can belong to another instance of the same component than the template it sits in. The trigger item of a submenu is written inside the content of the parent menu, yet it is the anchor of the submenu. Pass the root id of that instance as `rootId`. The `ids` of the surrounding component do not apply to it:
 *
 * ```html
 * <div {ui:ref(name: 'triggerItem', rootId: childId)}>Share</div>
 * ```
 *
 * ## Mark an element in the slot content of another component
 *
 * Slot content is evaluated in the template of the caller, so a bare `ui:ref` there does not know which component it belongs to and throws an exception. Pass `context` with the camelCase name of the enclosing component:
 *
 * ```html
 * <ui:combobox.root>
 *   <ui:combobox.content>
 *     <span {ui:ref(name: 'statusText', context: 'combobox')}>Searching products...</span>
 *   </ui:combobox.content>
 * </ui:combobox.root>
 * ```
 *
 * For markup that the client clones for every item, such as async search results, use [`ui:template`](./template).
 *
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
            ComponentUtility::assertValidRootId($explicitRootId);

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
