<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Annotations\AdditionalArgumentsAllowedAnnotation;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperNodeInitializedEventInterface;

/**
 * Renders arbitrary HTML attributes.
 *
 * When this ViewHelper is used inside a component, all attributes that are not defined as props will be collected and made available via this ViewHelper.
 *
 * ## Examples
 *
 * ### Usage on HTML elements
 * ```html
 * <ui:button class="my-button" data-test="123" disabled />
 * ```
 * Inside the button component, you can use this ViewHelper to render the attributes:
 * ```html
 * <button {ui:attributes()}></button>
 * ```
 * This will render:
 * ```html
 * <button class="my-button" data-test="123" disabled></button>
 * ```
 *
 * ### Usage on other components
 * When you need to pass the attributes to another component, you can use its attributes prop.
 * This prop is automatically added to components that use the `ui:attributes` ViewHelper inside them.
 * ```html
 * <ui:someComponent attributes="{ui:attributes()}" />
 * ```
 */
class AttributesViewHelper extends AbstractViewHelper implements ViewHelperNodeInitializedEventInterface
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('skip', 'string', 'A comma-separated list of attributes to skip');
        $this->registerArgument(
            'only',
            'string',
            'A comma-separated list of attributes to include. All other attributes will be skipped',
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
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Attributes ViewHelper is missing its rendering context.',
            1_788_100_002,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new \RuntimeException(
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
            throw new \RuntimeException(
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
