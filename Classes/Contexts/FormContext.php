<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Enum\FormState;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Type\DocType;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Service\ExtensionService;

// @mago-expect lint:too-many-methods
#[Autoconfigure(public: true)]
class FormContext extends AbstractComponentContext
{
    public function __construct(
        protected readonly MvcPropertyMappingConfigurationService $mvcPropertyMappingConfigurationService,
        protected readonly ExtensionService $extensionService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function afterRendering(string &$html): void
    {
        $identityFields = $this->renderHiddenIdentityField();
        foreach ($this->getNestedPersistedObjects() as $propertyPath => $nestedObject) {
            $identityFields .= $this->renderHiddenIdentityField($nestedObject, $propertyPath);
        }

        $html = str_replace(
            '</form>',
            $identityFields . $this->renderTrustedPropertiesField() . '</form>',
            $html,
        );

        foreach (array_keys($this->getFieldContextInformations()) as $id) {
            $this->getParentRenderingContext()->getViewHelperVariableContainer()->remove(FieldContext::class, $id);
        }
    }

    public function getResolvedAction(): ?string
    {
        if ((string)$this->get('actionUri') !== '') {
            return $this->get('actionUri');
        }

        $request = $this->getExtbaseRequestOrThrow();

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

    public function isIndicatorHidden(FormState $state): bool
    {
        return $this->getState() !== $state->value;
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
            $request = $this->getExtbaseRequestOrThrow();
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

    protected function renderTrustedPropertiesField(): string
    {
        $fieldNames = [];

        foreach ($this->getFieldContextInformations() as $fieldContextData) {
            if (!isset($fieldContextData['name'])) {
                continue;
            }

            $fieldNames[] = $this->prefixFieldName($fieldContextData['name'], $this->get('objectName'));
        }

        if ($this->getBoundPersistedObject() !== null) {
            $fieldNames[] = $this->prefixFieldName('__identity', $this->get('objectName'));
        }

        foreach (array_keys($this->getNestedPersistedObjects()) as $propertyPath) {
            $fieldNames[] = $this->prefixFieldName($propertyPath . '.__identity', $this->get('objectName'));
        }

        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken(
            $fieldNames,
            $this->getFieldNamePrefix(),
        );
        return (
            '<input type="hidden" name="' .
            htmlspecialchars($this->prefixFieldName('__trustedProperties')) .
            '" value="' .
            htmlspecialchars($requestHash) .
            '" ' .
            ($this->shouldUseXHtmlSlash() ? '/' : '') .
            '>'
        );
    }

    /**
     * Renders a hidden field carrying the technical identity (uid) of a persisted object, so
     * Extbase's `PersistentObjectConverter` treats the submission as a modification of that
     * persisted object rather than the creation of a new one. Mirrors
     * `AbstractFormViewHelper::renderHiddenIdentityField()`, which `<f:form>` relies on for the same
     * purpose and this Form - not built on top of `<f:form>` - otherwise has no equivalent for. Its
     * field name is also folded into `renderTrustedPropertiesField()`'s token above: whether
     * modification (vs. creation) of a persistent object is permitted is derived entirely from
     * whether `__identity` is present in that signed token, not from this raw field alone (see
     * `MvcPropertyMappingConfigurationService::modifyPropertyMappingConfiguration()`).
     *
     * Defaults to the form's own bound `object`; pass `$object`/`$propertyPath` for a persisted
     * sub-object nested under it (e.g. `eventRegistration.person`) - without its own `__identity`,
     * submitting the form would make Extbase create a new sub-object rather than update the existing
     * one, silently orphaning it.
     */
    protected function renderHiddenIdentityField(?AbstractDomainObject $object = null, ?string $propertyPath = null): string
    {
        $object ??= $this->getBoundPersistedObject();
        if (!$object instanceof AbstractDomainObject) {
            return '';
        }

        $fieldName = $propertyPath === null ? '__identity' : $propertyPath . '.__identity';
        $name = $this->prefixFieldName($fieldName, $this->get('objectName'));
        return (
            '<input type="hidden" name="' .
            htmlspecialchars($name) .
            '" value="' .
            htmlspecialchars((string)$object->getUid()) .
            '" ' .
            ($this->shouldUseXHtmlSlash() ? '/' : '') .
            '>'
        );
    }

    /**
     * The bound `object`, if it's a persisted domain object with a real uid - either not new, or a
     * clone of a previously-persisted one (e.g. the same instance re-rendered after a validation
     * error) - i.e. exactly the condition under which Extbase needs a `__identity` field to
     * recognize the submission as a modification rather than a creation.
     */
    protected function getBoundPersistedObject(): ?AbstractDomainObject
    {
        return $this->resolvePersistedObject($this->get('object'));
    }

    /**
     * Persisted domain sub-objects directly nested under the bound `object` (e.g. `person` on an
     * `EventRegistration`), keyed by their dot-notation property path. Derived from the property
     * paths of `<ui:field.root>`s actually used in the form (e.g. `person.name` yields `person`) -
     * fields on the root object itself (e.g. `ticketType`) are skipped, since those aren't sub-objects
     * and are already covered by `getBoundPersistedObject()`.
     *
     * Only one level deep: this doesn't recurse into further-nested sub-objects, which the form
     * primitives have no support for as field paths today (e.g. `person.address.city`) anyway.
     *
     * @return array<string, AbstractDomainObject>
     */
    protected function getNestedPersistedObjects(): array
    {
        $rootObject = $this->getBoundPersistedObject();
        if ($rootObject === null) {
            return [];
        }

        $nestedObjects = [];
        foreach ($this->getFieldContextInformations() as $fieldContextData) {
            $name = (string)($fieldContextData['name'] ?? '');
            if (!str_contains($name, '.')) {
                continue;
            }

            $propertyPath = substr($name, 0, strpos($name, '.'));
            if (isset($nestedObjects[$propertyPath])) {
                continue;
            }

            $nestedObject = $this->resolvePersistedObject(ObjectAccess::getProperty($rootObject, $propertyPath));
            if ($nestedObject !== null) {
                $nestedObjects[$propertyPath] = $nestedObject;
            }
        }

        return $nestedObjects;
    }

    /**
     * Normalizes a raw value (as found on a form's `object` argument, or a property of it) into the
     * persisted domain object it represents, or null if it isn't a persisted domain object at all -
     * shared by `getBoundPersistedObject()` and `getNestedPersistedObjects()`.
     */
    protected function resolvePersistedObject(mixed $object): ?AbstractDomainObject
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
        }

        if (!$object instanceof AbstractDomainObject) {
            return null;
        }

        if ($object->_isNew() && !$object->_isClone()) {
            return null;
        }

        return $object->getUid() !== null ? $object : null;
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

        $fieldPath = $this->parseFieldPath($fieldName);

        if (!in_array($objectName, [null, '', '0'], true)) {
            array_unshift($fieldPath, $objectName);
        }

        $prefix = $this->getFieldNamePrefix();
        if ($prefix !== '') {
            array_unshift($fieldPath, $prefix);
        }

        return $this->stringifyFieldPathAsBrackets($fieldPath);
    }

    /** @return list<string> */
    protected function parseFieldPath(string $fieldName): array
    {
        preg_match_all('/([^.[\]]+)|\[(.*?)\]/', $fieldName, $matches, PREG_SET_ORDER);

        $fieldPath = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $fieldPath[] = $match[1];
                continue;
            }

            $fieldPath[] = $match[2] ?? '';
        }

        return $fieldPath;
    }

    /** @param list<string> $fieldPath */
    protected function stringifyFieldPathAsBrackets(array $fieldPath): string
    {
        $fieldName = '';

        foreach ($fieldPath as $segment) {
            if ($segment === '') {
                $fieldName .= '[]';
                continue;
            }

            $fieldName .= $fieldName === '' ? $segment : '[' . $segment . ']';
        }

        return $fieldName;
    }

    protected function shouldUseXHtmlSlash(): bool
    {
        return DocType::createFromRequest($this->getRenderingContext()->getAttribute(ServerRequestInterface::class))->isXmlCompliant();
    }

    protected function getExtbaseRequestOrThrow(): RequestInterface
    {
        if (!$this->getRenderingContext()->hasAttribute(ServerRequestInterface::class)) {
            throw new \RuntimeException('No ServerRequestInterface found in rendering context attributes', 1765100022);
        }

        $request = $this->getRenderingContext()->getAttribute(ServerRequestInterface::class);
        if (!$request instanceof RequestInterface) {
            throw new \RuntimeException(
                'The ServerRequestInterface in rendering context attributes is not an Extbase RequestInterface',
                1765100023,
            );
        }

        return $request;
    }
}
