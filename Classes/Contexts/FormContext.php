<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Service\ExtensionService;

#[Autoconfigure(public: true)]
class FormContext extends AbstractComponentContext
{
    public function __construct(
        protected readonly MvcPropertyMappingConfigurationService $mvcPropertyMappingConfigurationService,
        protected readonly ExtensionService $extensionService,
        protected readonly PageRenderer $pageRenderer,
    ) {}

    public function afterRendering(string &$html): void
    {
        $html = str_replace(
            '</form>',
            $this->renderTrustedPropertiesField() . '</form>',
            $html
        );

        foreach (array_keys($this->getFieldContextInformations()) as $id) {
            $this->getParentRenderingContext()->getViewHelperVariableContainer()->remove(FieldContext::class, $id);
        }
    }

    public function getResolvedAction(): ?string
    {
        if (!empty($this->get('actionUri'))) {
            return $this->get('actionUri');
        }

        $request = $this->getExtbaseRequestOrThrow();

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uriBuilder
            ->reset()
            ->setRequest($request)
            // TODO: enable these options as arguments?
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

        $formActionUri = $uriBuilder->uriFor(
            $this->get('action') ?? null,
            $this->get('get') ?? [],
            $this->get('controller') ?? null,
            $this->get('extensionName') ?? null,
            $this->get('pluginName') ?? null
        );

        return $formActionUri;
    }

    // TODO: The form viewhelper has an argument to override the field name prefix, is this needed here?
    public function getFieldNamePrefix(): string
    {
        try {
            $request = $this->getExtbaseRequestOrThrow();
        } catch (\RuntimeException $e) {
            return '';
        }

        if (!empty($this->get('extensionName'))) {
            $extensionName = $this->get('extensionName');
        } else {
            $extensionName = $request->getControllerExtensionName();
        }

        if (!empty($this->get('pluginName'))) {
            $pluginName = $this->get('pluginName');
        } else {
            $pluginName = $request->getPluginName();
        }

        if ($extensionName !== null && $pluginName != null) {
            return $this->extensionService->getPluginNamespace($extensionName, $pluginName);
        }

        return '';
    }

    protected function renderTrustedPropertiesField(): string
    {
        $fieldNames = [];

        foreach ($this->getFieldContextInformations() as $fieldContextData) {
            if (isset($fieldContextData['name'])) {
                $fieldNames[] = $this->prefixFieldName($fieldContextData['name'], $this->get('objectName'));
            }
        }

        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken($fieldNames, $this->getFieldNamePrefix());
        return '<input type="hidden" name="' . htmlspecialchars($this->prefixFieldName('__trustedProperties')) . '" value="' . htmlspecialchars($requestHash) . '" ' . ($this->shouldUseXHtmlSlash() ? '/' : '') . '>';
    }

    protected function getFieldContextInformations(): array
    {
        return $this->getParentRenderingContext()->getViewHelperVariableContainer()->getAll(FieldContext::class);
    }

    protected function prefixFieldName(string $fieldName, ?string $objectName = null): string
    {
        if ($fieldName === '') {
            return '';
        }

        if (!empty($objectName)) {
            $fieldName = $objectName . '[' . $fieldName . ']';
        }

        $prefix = $this->getFieldNamePrefix();
        if ($prefix === '') {
            return $fieldName;
        }

        $fieldNameSegments = explode('[', $fieldName, 2);
        $fieldName = $prefix . '[' . $fieldNameSegments[0] . ']';

        if (count($fieldNameSegments) > 1) {
            $fieldName .= '[' . $fieldNameSegments[1];
        }

        return $fieldName;
    }

    protected function shouldUseXHtmlSlash(): bool
    {
        return $this->pageRenderer->getDocType()->isXmlCompliant();
    }

    protected function getExtbaseRequestOrThrow(): RequestInterface
    {
        if (!$this->getRenderingContext()->hasAttribute(ServerRequestInterface::class)) {
            throw new \RuntimeException('No ServerRequestInterface found in rendering context attributes', 1765100022);
        }

        $request = $this->getRenderingContext()->getAttribute(ServerRequestInterface::class);
        if (!$request instanceof RequestInterface) {
            throw new \RuntimeException('The ServerRequestInterface in rendering context attributes is not an Extbase RequestInterface', 1765100023);
        }

        return $request;
    }
}
