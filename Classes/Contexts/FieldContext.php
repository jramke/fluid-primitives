<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use Jramke\FluidPrimitives\Utility\ExtbaseFormFieldNamer;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

#[Autoconfigure(public: true)]
class FieldContext extends AbstractComponentContext
{
    public function __construct(
        private readonly ExtbaseFormFieldNamer $extbaseFormFieldNamer,
    ) {}

    public function beforeRendering(): void
    {
        $parentRenderingContext = $this->getParentRenderingContext();

        $variableContainer = $parentRenderingContext->getViewHelperVariableContainer();
        $variableContainer->add(self::class, Typed::string($this->get('rootId')), ['name' => $this->get('name')]);

        // A field nested inside a `FieldArray.Item` gets its `name` prefixed with the array's own
        // `name` and the item's row index (e.g. `people` + row 0 -> `people[0][firstName]`), or
        // with an empty index segment (`people[][firstName]`) inside the unfilled `itemTemplate`
        // stencil, where no real index exists yet - the client rewrites that placeholder to a real
        // index on each clone (see `ComponentHydrator.restampValue` in `Client/src/lib/
        // hydration.ts`). The child's own name is re-parsed rather than spliced in as a raw string,
        // so a dotted child name (`address.city`) canonicalizes to full brackets
        // (`people[0][address][city]`) instead of leaking a literal dot into one bracket segment.
        // Consumers set each row's `defaultValue` explicitly (see the FieldArray docs), so the
        // object-bound auto-resolution below is skipped for these fields rather than trying to
        // resolve a prefixed path against the form's bound object.
        $fieldArrayContext = ContextService::getFromRenderingContext($parentRenderingContext, 'fieldArray');
        if ($fieldArrayContext instanceof ComponentContextInterface && $this->has('name')) {
            $arrayName = Typed::stringOrNull($fieldArrayContext->get('name'));
            if ($arrayName !== null) {
                $index = Typed::intOrNull($fieldArrayContext->get('item.index'));
                $indexSegment = $index !== null ? (string)$index : '';
                $childPath = $this->extbaseFormFieldNamer->parseFieldPath((string)$this->get('name'));
                $this->set(
                    'name',
                    $this->extbaseFormFieldNamer->stringifyFieldPathAsBrackets([
                        $arrayName,
                        $indexSegment,
                        ...$childPath,
                    ]),
                );
                return;
            }
        }

        $formContext = ContextService::getFromRenderingContext($parentRenderingContext, 'form');
        if ($formContext instanceof ComponentContextInterface) {
            // Narrowed immediately below via is_object() - no Typed:: equivalent for objects.
            // @mago-expect analysis:mixed-assignment
            $formObject = $formContext->get('object');
            if (is_object($formObject) && $this->has('name')) {
                // A trailing "[]" (e.g. `name="a11yNeeds[]"`, the manual-bracket convention
                // CheckboxGroup and FileUpload's multi-file `name` prop both rely on) is a
                // submission-name detail, not part of the property path - ObjectAccess has no
                // concept of it and throws when it's left in.
                $name = (string)$this->get('name');
                $propertyPath = str_ends_with($name, '[]') ? substr($name, offset: 0, length: -2) : $name;
                $this->set('defaultValue', ObjectAccess::getPropertyPath($formObject, $propertyPath));
            }
        }

        // Canonicalize every other field's `name` to the same full-bracket wire format the
        // FieldArray branch above already uses (e.g. `person.country` -> `person[country]`), so the
        // client registry and toCanonicalFieldName() always have exactly one notation to match
        // against, whichever notation the template author wrote. Must run after the `defaultValue`
        // resolution above, which needs the original dot-notation path for ObjectAccess.
        if ($this->has('name')) {
            $canonicalPath = $this->extbaseFormFieldNamer->parseFieldPath((string)$this->get('name'));
            $this->set('name', $this->extbaseFormFieldNamer->stringifyFieldPathAsBrackets($canonicalPath));
        }
    }

    /**
     * @return array{name: ?string, disabled: ?bool, readOnly: ?bool, required: ?bool, invalid: ?bool, defaultValue: mixed, ids: array<string, string>}
     */
    public function getChildVariables(): array
    {
        $rootId = Typed::string($this->get('rootId'));

        $givenIds = (array)($this->get('ids') ?? []);
        /** @var array<string, string> $ids */
        $ids = array_merge($givenIds, [
            'control' => ComponentPartIdUtility::generatePartId('field', $rootId, 'control'),
            'label' => ComponentPartIdUtility::generatePartId('field', $rootId, 'label'),
        ]);

        return [
            'name' => Typed::stringOrNull($this->get('name')),
            'disabled' => Typed::boolOrNull($this->get('disabled')),
            'readOnly' => Typed::boolOrNull($this->get('readOnly')),
            'required' => Typed::boolOrNull($this->get('required')),
            'invalid' => Typed::boolOrNull($this->get('invalid')),
            'defaultValue' => $this->get('defaultValue') ?? null,
            'ids' => $ids,
        ];
    }
}
