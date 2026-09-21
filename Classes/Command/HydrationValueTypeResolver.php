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
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use UnitEnum;

/**
 * Resolves one client-facing prop's TS presence + value type for `ui:generate-hydration-types`,
 * per the plan's precedence order: `ClientTypeAwareInterface`/a registered converter always wins
 * (checked against a constructor-less probe instance of the prop's declared PHP class, since
 * codegen has no real rendered value to check against - the same reason
 * {@see \Jramke\FluidPrimitives\Contracts\ClientPropConverterInterface::supports()} is only ever
 * called with the class's own zero-state shape here); otherwise a `ui:prop`-sourced prop
 * is `Pick`ed from the primitive's own Props type when the (best-effort, see
 * {@see HydrationPropsSourceResolver}) resolved source appears to declare it; anything left maps
 * its PHP type string directly to a TS scalar/enum. A `#[ExposeToClient]` context prop tries the
 * same `Pick` first when it happens to share a name with a real Props-type key (it frequently does -
 * a context method commonly overrides a same-named ui:prop, e.g. `SelectContext::getTranslations()`
 * overriding the raw `translations` ui:prop), since `Pick` is always more precise than this class's
 * own direct mapping could ever be; only a context prop with no such match maps directly.
 */
final class HydrationValueTypeResolver
{
    public function __construct(
        private readonly ClientPropConverterRegistry $converterRegistry,
    ) {}

    public function resolveForArgument(
        string $clientBaseName,
        string $propName,
        ArgumentDefinition $definition,
        ?HydrationPropsTypeSource $propsSource,
    ): HydrationPropDefinition {
        $required = $this->isArgumentRequired($definition);
        $phpType = ltrim($definition->getType(), characters: '?');

        $objectResolution = $this->resolveObjectType($phpType, $clientBaseName, $propName);
        if ($objectResolution !== null) {
            return new HydrationPropDefinition(
                $propName,
                $required,
                $objectResolution[0],
                $objectResolution[1],
                pickFromPropsType: false,
            );
        }

        if ($propsSource !== null && $propsSource->hasKey($propName)) {
            return new HydrationPropDefinition(
                $propName,
                $required,
                tsType: '',
                tsImport: null,
                pickFromPropsType: true,
            );
        }

        $tsType = $this->mapScalarOrEnumType($phpType, $clientBaseName, $propName);
        return new HydrationPropDefinition($propName, $required, $tsType, tsImport: null, pickFromPropsType: false);
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
        // from the primitive's own Props type here too when it declares the same key, exactly like
        // resolveForArgument() does, rather than always falling back to a direct PHP-type mapping:
        // a direct mapping can only ever approximate the real field's shape (e.g. `Record<string,
        // unknown>` for what the Props type actually types as `IntlTranslations`), which breaks
        // `new Component(props)`'s own assignability the moment the two diverge.
        if ($propsSource !== null && $propsSource->hasKey($propName)) {
            return new HydrationPropDefinition(
                $propName,
                $required,
                tsType: '',
                tsImport: null,
                pickFromPropsType: true,
            );
        }

        $returnType = $method->getReturnType();
        $baseType = $returnType instanceof ReflectionNamedType ? $returnType->getName() : 'mixed';

        $objectResolution =
            $baseType !== 'mixed' && $baseType !== 'array'
                ? $this->resolveObjectType($baseType, $clientBaseName, $propName)
                : null;

        [$tsType, $tsImport] = $objectResolution ?? [
            $this->mapScalarOrEnumType($baseType, $clientBaseName, $propName),
            null,
        ];

        // excludeIfNull: false means ClientPropsContextExtractor sends `key: null` verbatim rather
        // than omitting the key - always-present, but genuinely nullable; excludeIfNull: true drops
        // a null result entirely instead, so the key is optional but never actually null when present.
        if (!$attribute->excludeIfNull) {
            $tsType .= ' | null';
        }

        return new HydrationPropDefinition(
            $propName,
            $required,
            tsType: $tsType,
            tsImport: $tsImport,
            pickFromPropsType: false,
        );
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
     * @return array{0: string, 1: ?string}|null [tsType, tsImport] when `$phpType` is a real,
     *   instantiable class implementing {@see ClientTypeAwareInterface} or matched by a registered
     *   converter; null when `$phpType` isn't a class at all (a scalar/enum, left to the caller's
     *   own Pick-or-map handling).
     */
    private function resolveObjectType(string $phpType, string $clientBaseName, string $propName): ?array
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
            return [$probe->getTsType(), $probe->getTsImport()];
        }

        $converter = $this->converterRegistry->findFor($probe, new ArgumentDefinition($propName, $phpType, '', false));
        if ($converter !== null) {
            return [$converter->getTsType(), $converter->getTsImport()];
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

    private function mapScalarOrEnumType(string $phpType, string $clientBaseName, string $propName): string
    {
        $mapped = match ($phpType) {
            'string' => 'string',
            'boolean', 'bool' => 'boolean',
            'integer', 'int', 'float', 'double' => 'number',
            'array' => 'Record<string, unknown>',
            'mixed', '' => 'unknown',
            default => null,
        };
        if ($mapped !== null) {
            return $mapped;
        }

        if (enum_exists($phpType)) {
            return $this->enumCasesToTsUnion($phpType);
        }

        throw new \RuntimeException(
            sprintf(
                'Cannot generate a hydration type for prop "%s" of component "%s": unmapped PHP type "%s".',
                $propName,
                $clientBaseName,
                $phpType,
            ),
            1_788_400_002,
        );
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
