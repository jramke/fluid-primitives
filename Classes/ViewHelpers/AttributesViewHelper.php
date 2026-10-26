<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Annotations\AdditionalArgumentsAllowedAnnotation;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 *
 * Renders the attributes a caller passes to your component that are not declared as props.
 *
 * ## Forward attributes to an element
 *
 * A caller adds attributes like on any other element:
 *
 * ```html
 * <ui:addToBasket data-product-id="42" disabled />
 * ```
 *
 * Place `{ui:attributes()}` on the element inside the component that should carry them:
 *
 * ```html
 * <button {ui:attributes()}>Add to basket</button>
 * ```
 *
 * This renders:
 *
 * ```html
 * <button data-product-id="42" disabled>Add to basket</button>
 * ```
 *
 * A template that uses `{class}` takes `class` as a prop and keeps it out of the attributes. Render it yourself there, for example with [`ui:cn`](./cn).
 *
 * ## Keep what the element sets itself
 *
 * A caller's attribute never replaces one the same element writes. This element sets `type`:
 *
 * ```html
 * <button type="button" {ui:attributes()}>Add to basket</button>
 * ```
 *
 * A caller passes `type` and `data-product-id`:
 *
 * ```html
 * <ui:addToBasket type="submit" data-product-id="42" />
 * ```
 *
 * This renders:
 *
 * ```html
 * <button type="button" data-product-id="42">Add to basket</button>
 * ```
 *
 * An attribute the element renders under a condition, like `{f:if(condition: closed, then: 'hidden')}`, counts as well, so a caller's `hidden` stays out while the condition is false. List attributes the template adds some other way, for example from a variable, in `skip`. Use `only` to render a few attributes and leave out the rest.
 *
 * ## Forward attributes to another component
 *
 * A component that uses `ui:attributes` gets an `attributes` prop that takes an array. Pass the attributes on through it with `asArray`:
 *
 * ```html
 * <ui:input attributes="{ui:attributes(asArray: true)}" />
 * ```
 *
 */
class AttributesViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'skip',
            'string',
            'A comma-separated list of attributes to leave out, on top of the ones the element sets itself',
        );
        $this->registerArgument(
            'only',
            'string',
            'A comma-separated list of attributes to render. All other attributes are left out',
        );
        $this->registerArgument(
            'asArray',
            'boolean',
            'If true, the attributes will be rendered as an array instead of a string. Useful when you need to pass the attributes to a Tag-ViewHelper with the `additionalAttributes` argument',
            false,
            false,
        );
    }

    public function render(): string|array
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'Attributes ViewHelper is missing its rendering context.',
            1_788_100_002,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new MissingContextException(
                'The attributes ViewHelper can only be used inside a component context.',
                1698255600,
            );
        }

        $asArray = Typed::bool($this->arguments['asArray']);

        // Narrowed immediately below via null/empty-array/instanceof checks - no single Typed:: call
        // covers that combination.
        // @mago-expect analysis:mixed-assignment
        $tagAttributes = $renderingContext->getViewHelperVariableContainer()->get(self::class, 'attributes');
        if ($tagAttributes === null || $tagAttributes === []) {
            return $asArray ? [] : '';
        }

        if (!$tagAttributes instanceof TagAttributes) {
            /** @var array<string, mixed> $rawAttributes */
            $rawAttributes = (array)$tagAttributes;
            $tagAttributes = new TagAttributes($rawAttributes);
        }

        if (count($tagAttributes) === 0) {
            return $asArray ? [] : '';
        }

        $skipProp = Typed::stringOrNull($this->arguments['skip']);
        $onlyProp = Typed::stringOrNull($this->arguments['only']);

        // TODO: maybe we can allow both?
        if ($skipProp && $onlyProp) {
            throw new InvalidPropValueException(
                'You cannot use both "skip" and "only" arguments at the same time.',
                1698255600,
            );
        }

        $skip = $skipProp ? GeneralUtility::trimExplode(',', $skipProp) : [];
        if ($skip !== []) {
            return $tagAttributes->renderWithSkip($skip, $asArray);
        }

        $only = $onlyProp ? GeneralUtility::trimExplode(',', $onlyProp) : [];
        if ($only !== []) {
            return $tagAttributes->renderWithOnly($only, $asArray);
        }

        return $asArray ? $tagAttributes->renderAsArray() : (string)$tagAttributes;
    }

    // Unconditional, no early-return guard: unlike AsChildViewHelper's reserved-name-backed
    // 'asChild' key, 'attributes' isn't a reserved prop name and can legitimately already exist in
    // $argumentDefinitions from an unrelated ui:useProps import - bailing on "already present" would
    // then also wrongly skip attaching AdditionalArgumentsAllowedAnnotation for this template's own,
    // real ui:attributes() usage. Redeclaring with the same shape is harmless.
    public static function nodeInitializedEvent(
        ViewHelperNode $node,
        array $arguments,
        ParsingState $parsingState,
    ): void {
        $argumentDefinitions = $parsingState->getArgumentDefinitions();

        $argumentDefinitions['attributes'] = new ArgumentDefinition(
            'attributes',
            'array',
            'Additional attributes that should be rendered on the component where ui:attributes is used.',
            false,
            [],
            null,
            [new AdditionalArgumentsAllowedAnnotation()],
        );

        $parsingState->setArgumentDefinitions($argumentDefinitions);
    }
}
