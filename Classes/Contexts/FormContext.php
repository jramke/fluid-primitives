<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Enum\FormState;
use Jramke\FluidPrimitives\Service\ExtbaseFormHiddenFieldsRenderer;
use Jramke\FluidPrimitives\Traits\HasIndicatorStateTrait;
use Jramke\FluidPrimitives\Utility\ExtbasePersistedObjectResolver;
use Jramke\FluidPrimitives\Utility\ExtbaseRequestResolver;
use Jramke\FluidPrimitives\Utility\Typed;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Type\DocType;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Service\ExtensionService;

// @mago-expect lint:too-many-methods
#[Autoconfigure(public: true)]
class FormContext extends AbstractComponentContext
{
    use HasIndicatorStateTrait;

    public function __construct(
        private readonly ExtbasePersistedObjectResolver $persistedObjectResolver,
        private readonly ExtbaseFormHiddenFieldsRenderer $hiddenFieldsRenderer,
        private readonly ExtbaseRequestResolver $requestResolver,
        protected readonly ExtensionService $extensionService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function afterRendering(string &$html): void
    {
        $fieldContextInformations = $this->getFieldContextInformations();
        $objects = $this->persistedObjectResolver->resolveForForm($this->get('object'), $fieldContextInformations);
        $objectName = Typed::stringOrNull($this->get('objectName'));
        $fieldNamePrefix = $this->getFieldNamePrefix();
        $xhtmlCompliant = $this->shouldUseXHtmlSlash();

        $hiddenFields = $this->hiddenFieldsRenderer->renderIdentityFields(
            $objects,
            $objectName,
            $fieldNamePrefix,
            $xhtmlCompliant,
        );
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
        $actionUri = Typed::string($this->get('actionUri'));
        if ($actionUri !== '') {
            return $actionUri;
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

        $pageUid = Typed::int($this->get('pageUid'));
        if ($pageUid > 0) {
            $uriBuilder->setTargetPageUid($pageUid);
        }

        $extensionName = $this->getExtensionNameFromPropOrRequest($request);

        return $uriBuilder->uriFor(
            Typed::stringOrNull($this->get('action')),
            Typed::arrayOrNull($this->get('get')) ?? [],
            Typed::stringOrNull($this->get('controller')),
            $extensionName,
            $this->resolveTargetPluginName($extensionName, $request),
        );
    }

    public function getState(): string
    {
        return FormState::Ready->value;
    }

    public function getContentHidden(): bool
    {
        return in_array($this->getState(), [FormState::Error->value, FormState::Success->value], strict: true);
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
        $extensionNameProp = Typed::string($this->get('extensionName'));
        $pluginNameProp = Typed::string($this->get('pluginName'));

        try {
            $request = $this->requestResolver->resolveOrThrow($this->getRenderingContext());
        } catch (\RuntimeException) {
            if ($extensionNameProp === '' || $pluginNameProp === '') {
                throw new \RuntimeException(
                    'Cannot determine the field name prefix for <ui:form.root>: no Extbase request is available ' .
                    'and "extensionName"/"pluginName" were not both given explicitly.',
                    1_788_200_005,
                );
            }

            return $this->extensionService->getPluginNamespace($extensionNameProp, $pluginNameProp);
        }

        $extensionName = $this->getExtensionNameFromPropOrRequest($request);

        return $this->extensionService->getPluginNamespace($extensionName, $this->resolveTargetPluginName(
            $extensionName,
            $request,
        ));
    }

    private function getExtensionNameFromPropOrRequest(RequestInterface $request): string
    {
        $extensionNameProp = Typed::string($this->get('extensionName'));
        return $extensionNameProp === '' ? $request->getControllerExtensionName() : $extensionNameProp;
    }

    /**
     * The plugin the form submits to, which is the one whose namespace prefixes its fields: an explicit
     * `pluginName`, else the plugin registered for `controller`/`action` (the lookup UriBuilder::uriFor()
     * makes itself), else the plugin rendering the form.
     */
    private function resolveTargetPluginName(string $extensionName, RequestInterface $request): string
    {
        $pluginNameProp = Typed::string($this->get('pluginName'));
        if ($pluginNameProp !== '') {
            return $pluginNameProp;
        }

        $controller = Typed::string($this->get('controller'));
        if ($controller !== '') {
            $resolved = $this->extensionService->getPluginNameByAction(
                $extensionName,
                $controller,
                Typed::stringOrNull($this->get('action')),
            );
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $request->getPluginName();
    }

    /**
     * @return array<string, array{name?: string}>
     */
    private function getFieldContextInformations(): array
    {
        // The intermediate variable isn't redundant here despite the immediate return - it's what the
        // @var below narrows, since ViewHelperVariableContainer::getAll() itself only declares a bare
        // `array` return and FieldContext is the only thing that ever adds to this bucket.
        // @mago-expect lint:inline-variable-return
        /** @var array<string, array{name?: string}> $fieldContextInformations */
        $fieldContextInformations = $this
            ->getParentRenderingContext()
            ->getViewHelperVariableContainer()
            ->getAll(FieldContext::class);

        return $fieldContextInformations;
    }

    private function shouldUseXHtmlSlash(): bool
    {
        return DocType::createFromRequest($this->getRenderingContext()->getAttribute(ServerRequestInterface::class))->isXmlCompliant();
    }
}
