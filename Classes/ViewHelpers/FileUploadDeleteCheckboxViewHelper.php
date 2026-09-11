<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\FormContext;
use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Service\ContextService;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders a checkbox that, when checked and the enclosing form is submitted, marks an already
 * uploaded `FileReference` for deletion - the counterpart to the `FileUpload` primitive, which only
 * ever handles files newly picked in the browser.
 *
 * Reimplements TYPO3 core's `<f:form.uploadDeleteCheckbox>` for the `Form`/`Field` primitives:
 * it produces the exact same `TYPO3\CMS\Extbase\Service\FileHandlingService`-compatible HMAC-signed
 * `@delete` token, but resolves the Extbase argument name/prefix from the surrounding
 * `<ui:form.root>` context instead of `<f:form>`'s own (unused here) ViewHelperVariableContainer
 * state.
 *
 * Must be used inside a `<ui:form.root objectName="...">`, where `objectName` matches the
 * controller action argument name. Use `property` to declare which model property the file
 * reference belongs to - when used inside a `<ui:field.root name="...">`, the field's `name` is
 * used as a fallback (core's version always requires `property` explicitly).
 *
 * ## Example
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
        $formContext = ContextService::getFromRenderingContext($this->renderingContext, 'form');
        if (!$formContext instanceof FormContext) {
            throw new \RuntimeException(
                'ui:fileUploadDeleteCheckbox can only be used inside a <ui:form.root>.',
                1_788_000_001,
            );
        }

        $objectName = (string)($formContext->get('objectName') ?? '');
        if ($objectName === '') {
            throw new \RuntimeException(
                'ui:fileUploadDeleteCheckbox requires the enclosing <ui:form.root> to have an "objectName" matching the Extbase action argument name.',
                1_788_000_002,
            );
        }

        $fieldContext = ContextService::getFromRenderingContext($this->renderingContext, 'field');
        $property = $this->arguments['property'] ?? $fieldContext?->get('name');
        if (!is_string($property) || $property === '') {
            throw new \RuntimeException(
                'ui:fileUploadDeleteCheckbox requires a "property" argument, unless used inside a <ui:field.root name="...">.',
                1_788_000_003,
            );
        }

        /** @var FileReference $fileReference */
        $fileReference = $this->arguments['fileReference'];

        $token = $this->hashService->appendHmac(
            (string)json_encode([
                'fileReference' => $fileReference->getUid(),
                'property' => $property,
            ], JSON_THROW_ON_ERROR),
            '@delete',
        );

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

        return '<input ' . $attributes . ' />';
    }
}
