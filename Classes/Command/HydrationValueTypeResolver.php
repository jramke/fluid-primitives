<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use BackedEnum;
use Jramke\FluidPrimitives\Annotations\RequiredAtRuntimeArgumentAnnotation;
use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface;
use Jramke\FluidPrimitives\Registry\ClientPropConverterRegistry;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\TypeScriptTransformer\Actions\TranspilePhpStanTypeToTypeScriptNodeAction;
use Spatie\TypeScriptTransformer\TypeResolvers\DocTypeResolver;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNull;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptReference;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnion;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use UnitEnum;

/**
 * Resolves one client-facing prop's TS presence + value type for `ui:generate-hydration-types`,
 * per the plan's precedence order: `ClientTypeAwareInterface`/a registered converter always wins
 * (checked against a constructor-less probe instance of the prop's declared PHP class, since
 * codegen has no real rendered value to check against - the same reason
 * {@see \Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface::supports()} is only ever
 * called with the class's own zero-state shape here); otherwise a `ui:prop`-sourced prop is `Pick`ed
 * from the primitive's own Props type whenever that (best-effort, see
 * {@see HydrationPropsSourceResolver}) source resolved and doesn't list this prop in its own
 * {@see HydrationPropsTypeSource::$excludedKeys} - beyond that one narrow, statically-known
 * exception there's no further per-key existence check, `npm run types`/`--check` is what catches a
 * wrong guess; a prop falls back to mapping its PHP type string directly to a TS scalar/enum when no
 * Props source resolved at all, or when it's excluded. A `#[ExposeToClient]` context prop tries the
 * same `Pick` first under the same condition, since `Pick` is always more precise than this class's
 * own direct mapping could ever be (a context method commonly overrides a same-named ui:prop, e.g.
 * `SelectContext::getTranslations()` overriding the raw `translations` ui:prop); only when no
 * source resolved, or the prop is excluded, does a context prop map directly.
 */
final class HydrationValueTypeResolver
{
    public function __construct(
        private readonly ClientPropConverterRegistry $converterRegistry,
        private readonly DocTypeResolver $docTypeResolver = new DocTypeResolver(),
        private readonly TranspilePhpStanTypeToTypeScriptNodeAction $typeTranspiler = new TranspilePhpStanTypeToTypeScriptNodeAction(),
    ) {}

    public function resolveForArgument(
        string $clientBaseName,
        string $propName,
        ArgumentDefinition $definition,
        ?HydrationPropsTypeSource $propsSource,
    ): HydrationPropDefinition {
        $required = $this->isArgumentRequired($definition);
        $phpType = ltrim($definition->getType(), characters: '?');

        $shapeReference = $this->resolveShapeReference($phpType, $clientBaseName, $propName);
        if ($shapeReference !== null) {
            return new HydrationPropDefinition($propName, $required, $shapeReference, pickFromPropsType: false);
        }

        if ($propsSource !== null && !in_array($propName, $propsSource->excludedKeys, strict: true)) {
            return new HydrationPropDefinition($propName, $required, tsType: null, pickFromPropsType: true);
        }

        $tsType = $this->mapScalarOrEnumType($phpType, $clientBaseName, $propName);
        return new HydrationPropDefinition($propName, $required, $tsType, pickFromPropsType: false);
    }

    public function resolveForContextMethod(
        string $clientBaseName,
        string $propName,
        ReflectionMethod $method,
        ExposeToClient $attribute,
        ?HydrationPropsTypeSource $propsSource,
    ): HydrationPropDefinition {
        $required = !$attribute->excludeIfNull;

        // A context method frequently overrides a same-named ui:prop with a server-computed value
        // (e.g. SelectContext::getTranslations() overriding the raw `translations` ui:prop) - Pick
        // from the primitive's own Props type here too whenever one resolved (and doesn't exclude
        // this prop), exactly like resolveForArgument() does, rather than always falling back to a
        // direct PHP-type mapping: a direct mapping can only ever approximate the real field's shape
        // (e.g. `Record<string, unknown>` for what the Props type actually types as
        // `IntlTranslations`), which breaks `new Component(props)`'s own assignability the moment
        // the two diverge.
        if ($propsSource !== null && !in_array($propName, $propsSource->excludedKeys, strict: true)) {
            return new HydrationPropDefinition($propName, $required, tsType: null, pickFromPropsType: true);
        }

        $returnType = $method->getReturnType();
        $baseType = $returnType instanceof ReflectionNamedType ? $returnType->getName() : 'mixed';

        $tsType = $this->resolveShapeReference($baseType, $clientBaseName, $propName) ?? $this->mapScalarOrEnumType(
            $baseType,
            $clientBaseName,
            $propName,
        );

        // excludeIfNull: false means ClientPropsContextExtractor sends `key: null` verbatim rather
        // than omitting the key - always-present, but genuinely nullable; excludeIfNull: true drops
        // a null result entirely instead, so the key is optional but never actually null when present.
        if (!$attribute->excludeIfNull) {
            $tsType = new TypeScriptUnion([$tsType, new TypeScriptNull()]);
        }

        return new HydrationPropDefinition($propName, $required, $tsType, pickFromPropsType: false);
    }

    /**
     * ui:prop, no default, `optional="{false}"` -> required and now runtime-enforced (see
     * ComponentHydrationCollector's own null-safety fix); a real (non-null) default -> required,
     * since the resolved value is never absent even when the call site doesn't pass one; and
     * `requiredAtRuntime="{true}"` (e.g. Select's `collection`, FieldArray's `name`/`itemCount`) ->
     * already runtime-enforced by PropViewHelper itself (checked for *presence*, independent of
     * ArgumentDefinition::isRequired()), so it gets the same required treatment here even though
     * it's declared `optional="{true}"` on the Fluid side - the plan's own Select.hydration.ts
     * example types `collection` as required, not `Partial`.
     */
    private function isArgumentRequired(ArgumentDefinition $definition): bool
    {
        if ($definition->isRequired() || $definition->getDefaultValue() !== null) {
            return true;
        }

        foreach ($definition->getAnnotations() as $annotation) {
            if ($annotation instanceof RequiredAtRuntimeArgumentAnnotation) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return TypeScriptNode|null A {@see TypeScriptReference} pointing at `$phpType`'s declared
     *   {@see ClientTypeAwareInterface}/converter shape class when `$phpType` is a real, instantiable
     *   class; null when `$phpType` isn't a class at all (a scalar/enum, left to the caller's own
     *   Pick-or-map handling). `ConnectReferencesAction` resolves the reference against whichever
     *   `#[TypeScript]`-transformed `Transformed` the shape class produced, wherever it lands -
     *   {@see HydrationTransformedProvider} never has to know that path itself.
     */
    private function resolveShapeReference(string $phpType, string $clientBaseName, string $propName): ?TypeScriptNode
    {
        // class_exists() also returns true for an enum (it shares the same symbol table as
        // classes/interfaces/traits) - excluded here so a backed/unit enum always falls through to
        // mapScalarOrEnumType()'s own union-of-cases handling instead of being (wrongly) probed as
        // an instantiable object.
        if (enum_exists($phpType) || !class_exists($phpType)) {
            return null;
        }

        $probe = (new ReflectionClass($phpType))->newInstanceWithoutConstructor();

        if ($probe instanceof ClientTypeAwareInterface) {
            return TypeScriptReference::referencingPhpClass($probe->getTsShapeClass());
        }

        $converter = $this->converterRegistry->findFor($probe, new ArgumentDefinition($propName, $phpType, '', false));
        if ($converter !== null) {
            return TypeScriptReference::referencingPhpClass($converter->getTsShapeClass());
        }

        throw new \RuntimeException(
            sprintf(
                'Cannot generate a hydration type for prop "%s" of component "%s": PHP type "%s" implements ' .
                'neither ClientTypeAwareInterface nor a registered ClientPropConverterInterface.',
                $propName,
                $clientBaseName,
                $phpType,
            ),
            1_788_400_001,
        );
    }

    /**
     * Delegates to spatie's own PHPStan-style type-string parser/transpiler instead of a hand-rolled
     * `match` - natively handles union types (`float|integer|array`, the real `Slider::defaultValue`
     * shape that the old `match` statement had no case for at all) and nested/typed-array syntax
     * (`string[]`), not just the single-level scalar cases the old table covered. An enum still goes
     * through {@see enumCasesToTsUnion()}, not this resolver: feeding a bare enum FQCN through it
     * produces a dangling `TypeScriptReference` to the enum's own (never `#[TypeScript]`-transformed)
     * class, not the backed-value union we actually want on the wire - confirmed empirically, not a
     * documented limitation.
     */
    private function mapScalarOrEnumType(string $phpType, string $clientBaseName, string $propName): TypeScriptNode
    {
        if (enum_exists($phpType)) {
            return new TypeScriptRaw($this->enumCasesToTsUnion($phpType));
        }

        // DocTypeResolver::type() throws on an empty string; Fluid's own ArgumentDefinition::getType()
        // returns '' for an untyped prop, same meaning as an explicit "mixed" here.
        $typeString = $phpType !== '' ? $phpType : 'mixed';

        try {
            return $this->typeTranspiler->execute($this->docTypeResolver->type($typeString), phpClassNode: null);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot generate a hydration type for prop "%s" of component "%s": unmapped PHP type "%s".',
                    $propName,
                    $clientBaseName,
                    $phpType,
                ),
                1_788_400_002,
                $exception,
            );
        }
    }

    /**
     * Unions each case's actual wire value - the backing value for a `BackedEnum` (what
     * `EnumUtility::normalize()` actually sends to the client), or the bare case name for a plain
     * `UnitEnum` - not `DocsUtility::getCasesStringFromType()`'s docs-table precedent of always
     * using case names, which would be wrong for a backed enum's real wire shape.
     *
     * @param class-string $enumClass
     */
    private function enumCasesToTsUnion(string $enumClass): string
    {
        /** @var list<UnitEnum> $cases */
        $cases = $enumClass::cases();

        $literals = array_map($this->enumCaseToTsLiteral(...), $cases);

        return implode(' | ', $literals);
    }

    private function enumCaseToTsLiteral(UnitEnum $case): string
    {
        if (!$case instanceof BackedEnum) {
            return "'" . $case->name . "'";
        }

        return is_int($case->value) ? (string)$case->value : "'" . $case->value . "'";
    }
}
