<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Enum\FormState;
use Jramke\FluidPrimitives\Traits\HasIndicatorStateTrait;
use Jramke\FluidPrimitives\Utility\ExtbaseFormHiddenFieldsRenderer;
use Jramke\FluidPrimitives\Utility\ExtbasePersistedObjectResolver;
use Jramke\FluidPrimitives\Utility\ExtbaseRequestResolver;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Type\DocType;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Service\ExtensionService;

#[Autoconfigure(public: true)]
class FormContext extends AbstractComponentContext
{
    use HasIndicatorStateTrait;

    private readonly ExtbasePersistedObjectResolver $persistedObjectResolver;

    private readonly ExtbaseFormHiddenFieldsRenderer $hiddenFieldsRenderer;

    private readonly ExtbaseRequestResolver $requestResolver;

    public function __construct(
        MvcPropertyMappingConfigurationService $mvcPropertyMappingConfigurationService,
        protected readonly ExtensionService $extensionService,
        private readonly UriBuilder $uriBuilder,
    ) {
        $this->persistedObjectResolver = new ExtbasePersistedObjectResolver();
        $this->hiddenFieldsRenderer = new ExtbaseFormHiddenFieldsRenderer($mvcPropertyMappingConfigurationService);
        $this->requestResolver = new ExtbaseRequestResolver();
    }

    public function afterRendering(string &$html): void
    {
        $fieldContextInformations = $this->getFieldContextInformations();
        $objects = $this->persistedObjectResolver->resolveForForm($this->get('object'), $fieldContextInformations);
        $objectName = $this->get('objectName');
        $fieldNamePrefix = $this->getFieldNamePrefix();
        $xhtmlCompliant = $this->shouldUseXHtmlSlash();

        $hiddenFields = $this->hiddenFieldsRenderer->renderIdentityFields($objects, $objectName, $fieldNamePrefix, $xhtmlCompliant);
        $hiddenFields .= $this->hiddenFieldsRenderer->renderTrustedPropertiesField(
            $fieldContextInformations,
            $objects,
            $objectName,
            $fieldNamePrefix,
            $xhtmlCompliant,
        );

        $html = str_replace('</form>', $hiddenFields . '</form>', $html);

        foreach (array_keys($fieldContextInformations) as $id) {
            $this->getParentRenderingContext()->getViewHelperVariableContainer()->remove(FieldContext::class, $id);
        }
    }

    public function getResolvedAction(): ?string
    {
        if ((string)$this->get('actionUri') !== '') {
            return $this->get('actionUri');
        }

        $request = $this->requestResolver->resolveOrThrow($this->getRenderingContext());

        $uriBuilder = $this->uriBuilder;
        $uriBuilder->reset()->setRequest($request)// TODO: enable these options as arguments?
        // ->setTargetPageType((int)($this->arguments['pageType'] ?? 0))
        // ->setNoCache((bool)($this->arguments['noCache'] ?? false))
        // ->setSection($this->arguments['section'] ?? '')
        // ->setCreateAbsoluteUri((bool)($this->arguments['absolute'] ?? false))
        // ->setArguments(isset($this->arguments['additionalParams']) ? (array)$this->arguments['additionalParams'] : [])
        // ->setAddQueryString($this->arguments['addQueryString'] ?? false)
        // ->setArgumentsToBeExcludedFromQueryString(isset($this->arguments['argumentsToBeExcludedFromQueryString']) ? (array)$this->arguments['argumentsToBeExcludedFromQueryString'] : [])
        // ->setFormat($this->arguments['format'] ?? '')
        ;

        $pageUid = (int)($this->get('pageUid') ?? 0);
        if ($pageUid > 0) {
            $uriBuilder->setTargetPageUid($pageUid);
        }

        return $uriBuilder->uriFor(
            $this->get('action') ?? null,
            $this->get('get') ?? [],
            $this->get('controller') ?? null,
            $this->get('extensionName') ?? null,
            $this->get('pluginName') ?? null,
        );
    }

    public function getState(): string
    {
        return FormState::Ready->value;
    }

    public function getContentHidden(): bool
    {
        return in_array($this->getState(), [FormState::Error->value, FormState::Success->value], true);
    }

    public function getErrorTextHidden(): bool
    {
        return $this->getState() !== FormState::Error->value;
    }

    public function getSuccessTextHidden(): bool
    {
        return $this->getState() !== FormState::Success->value;
    }

    // TODO: The form viewhelper has an argument to override the field name prefix, is this needed here?
    // Public so other ViewHelpers rendered inside the form's slot content (e.g.
    // FileUploadDeleteCheckboxViewHelper) can build correctly-prefixed field names of their own.
    public function getFieldNamePrefix(): string
    {
        try {
            $request = $this->requestResolver->resolveOrThrow($this->getRenderingContext());
        } catch (\RuntimeException) {
            return '';
        }

        $extensionName = (string)$this->get('extensionName') === ''
            ? $request->getControllerExtensionName()
            : $this->get('extensionName');

        $pluginName = (string)$this->get('pluginName') === '' ? $request->getPluginName() : $this->get('pluginName');

        if ($extensionName !== null && $pluginName !== null) {
            return $this->extensionService->getPluginNamespace($extensionName, $pluginName);
        }

        return '';
    }

    protected function getFieldContextInformations(): array
    {
        return $this->getParentRenderingContext()->getViewHelperVariableContainer()->getAll(FieldContext::class);
    }

    protected function shouldUseXHtmlSlash(): bool
    {
        return DocType::createFromRequest($this->getRenderingContext()->getAttribute(ServerRequestInterface::class))->isXmlCompliant();
    }
}
