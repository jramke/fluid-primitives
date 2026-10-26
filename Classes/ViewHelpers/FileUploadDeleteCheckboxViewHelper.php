<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\FormContext;
use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Exception\MissingPropException;
use Jramke\FluidPrimitives\Service\ContextService;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Renders a checkbox that deletes an already uploaded file when the form is submitted.
 *
 * ```html
 * <ui:form.root action="update" objectName="conference" object="{conference}">
 *     <ui:field.root name="logo">
 *         <f:if condition="{conference.logo}">
 *             <label>
 *                 <ui:fileUploadDeleteCheckbox fileReference="{conference.logo}" />
 *                 Delete current logo
 *             </label>
 *         </f:if>
 *         <ui:fileUpload.root name="logo">
 *             <!-- ... -->
 *         </ui:fileUpload.root>
 *     </ui:field.root>
 * </ui:form.root>
 * ```
 *
 * It complements the `FileUpload` primitive, which handles files picked in the browser, for a `FileReference` that is already stored. The checkbox carries the same signed `@delete` value as TYPO3's `<f:form.uploadDeleteCheckbox>`.
 *
 * Place it inside a `ui:form.root` whose `objectName` matches the name of the action argument, otherwise it throws an exception. `property` names the model property the file reference belongs to. Inside a `ui:field.root` it defaults to the `name` of the field.
 *
 */
class FileUploadDeleteCheckboxViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly HashService $hashService,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'fileReference',
            FileReference::class,
            'The existing file reference to allow deleting.',
            true,
        );
        $this->registerArgument(
            'property',
            'string',
            'The model property (or dot-path) the file reference belongs to. Inherited from a surrounding `ui:field.root` when omitted.',
            false,
        );
        $this->registerArgument('id', 'string', 'The id attribute of the checkbox.', false);
        $this->registerArgument('class', 'string', 'The class attribute of the checkbox.', false);
    }

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'FileUploadDeleteCheckbox ViewHelper is missing its rendering context.',
            1_788_100_006,
        );

        $formContext = ContextService::getFromRenderingContext($renderingContext, 'form');
        if (!$formContext instanceof FormContext) {
            throw new MissingContextException(
                'ui:fileUploadDeleteCheckbox can only be used inside a <ui:form.root>.',
                1_788_000_001,
            );
        }

        $objectName = (string)($formContext->get('objectName') ?? '');
        if ($objectName === '') {
            throw new MissingPropException(
                'ui:fileUploadDeleteCheckbox requires the enclosing <ui:form.root> to have an "objectName" matching the Extbase action argument name.',
                1_788_000_002,
            );
        }

        $fieldContext = ContextService::getFromRenderingContext($renderingContext, 'field');
        // Checked with is_string() rather than Typed::stringOrNull() below - the latter would also
        // accept and coerce numeric scalars, which isn't a valid property name here.
        // @mago-expect analysis:mixed-assignment
        $property = $this->arguments['property'] ?? $fieldContext?->get('name');
        if (!is_string($property) || $property === '') {
            throw new MissingPropException(
                'ui:fileUploadDeleteCheckbox requires a "property" argument, unless used inside a <ui:field.root name="...">.',
                1_788_000_003,
            );
        }

        /** @var FileReference $fileReference */
        $fileReference = $this->arguments['fileReference'];

        $token = $this->hashService->appendHmac(json_encode([
            'fileReference' => $fileReference->getUid(),
            'property' => $property,
        ], JSON_THROW_ON_ERROR), '@delete');

        $segments = array_filter(
            [$formContext->getFieldNamePrefix(), '@delete', $objectName],
            static fn(string $segment): bool => $segment !== '',
        );
        $name = (string)array_shift($segments);
        foreach ($segments as $segment) {
            $name .= '[' . $segment . ']';
        }
        $name .= '[]';

        $attributes = new TagAttributes([
            'type' => 'checkbox',
            'name' => $name,
            'value' => $token,
            'id' => $this->arguments['id'] ?? null,
            'class' => $this->arguments['class'] ?? null,
        ]);

        return '<input ' . (string)$attributes . ' />';
    }
}
