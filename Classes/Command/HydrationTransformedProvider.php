<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;
use LogicException;
use Spatie\TypeScriptTransformer\References\CustomReference;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\TransformedProviders\TransformedProvider;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptAlias;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptGeneric;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptIdentifier;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptIntersection;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptLiteral;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptObject;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptString;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnion;

/**
 * Builds one composite `<Name>HydrationProps` `Transformed` per root component - the direct
 * replacement for `HydrationFileWriter`'s old manual `Pick<>`/`Partial<>`/intersection string
 * concatenation. The data gathering itself ({@see HydrationComponentPropsCollector},
 * {@see HydrationPropsSourceResolver}'s Zag-package import detection, the required/optional
 * presence rules) is untouched - only how the gathered {@see HydrationPropDefinition} list becomes
 * real spatie TS nodes changes. Each component gets its own {@see HydrationTypeScriptWriter}
 * instance (set via `Transformed::setWriter()`), which is what actually names its own output file
 * `<Name>.hydration.ts` - see that class's own docblock for why a per-`Transformed` writer, not
 * spatie's stock `ModuleWriter`, is what makes an exact per-component filename possible. No backing
 * PHP class describes a component's own composite props type as a whole, so each `Transformed`'s
 * identity is a {@see CustomReference}, not a `ClassStringReference` - nothing else ever needs to
 * reference one of these back, they're always the outermost, never-embedded type.
 */
final readonly class HydrationTransformedProvider implements TransformedProvider
{
    private const string REFERENCE_GROUP = 'fluid_primitives_hydration';

    /**
     * @param list<RootComponentLocation> $locations
     */
    public function __construct(
        private ComponentCollectionInterface $collection,
        private array $locations,
        private ?string $outputDir,
        private HydrationComponentPropsCollector $propsCollector,
    ) {}

    /**
     * @return array<Transformed>
     */
    public function provide(): array
    {
        return array_map($this->provideForLocation(...), $this->locations);
    }

    private function provideForLocation(RootComponentLocation $location): Transformed
    {
        $collected = $this->propsCollector->collect($this->collection, $location);
        $propDefinitions = $collected['propDefinitions'];
        $propsSource = $collected['propsSource'];

        $node = new TypeScriptAlias(
            $location->name . 'HydrationProps',
            new TypeScriptIntersection($this->intersectionParts($propDefinitions, $propsSource)),
        );

        $transformed = new Transformed(
            $node,
            new CustomReference(self::REFERENCE_GROUP, $location->name),
            location: [],
        );

        // Only carried when at least one prop is actually Pick()ed from it - unlike a real spatie
        // reference, an unused raw import line isn't automatically dropped, and ScrollArea (no
        // client-marked prop maps to its own Props type at all) would otherwise get a dead import.
        $usesPropsSource = in_array(true, array_column($propDefinitions, 'pickFromPropsType'), strict: true);

        $transformed->setWriter(
            new HydrationTypeScriptWriter(
                $this->targetFile($location),
                $this->declareModuleFooter($location->name),
                $usesPropsSource ? $propsSource?->tsImport : null,
            ),
        );

        return $transformed;
    }

    /**
     * @param list<HydrationPropDefinition> $propDefinitions
     * @return list<TypeScriptNode>
     */
    private function intersectionParts(array $propDefinitions, ?HydrationPropsTypeSource $propsSource): array
    {
        $requiredPickKeys = [];
        $optionalPickKeys = [];
        $properties = [];

        foreach ($propDefinitions as $definition) {
            if ($definition->pickFromPropsType) {
                if (!$definition->required) {
                    $optionalPickKeys[] = $definition->name;
                    continue;
                }
                $requiredPickKeys[] = $definition->name;
                continue;
            }

            $tsType = $definition->tsType ?? throw new LogicException(sprintf(
                'Prop "%s" has neither a value type nor pickFromPropsType set.',
                $definition->name,
            ));
            $properties[] = new TypeScriptProperty($definition->name, $tsType, isOptional: !$definition->required);
        }

        $parts = [$this->baseIdsObject()];

        if ($requiredPickKeys !== [] && $propsSource !== null) {
            $parts[] = $this->pick($propsSource->typeName, $requiredPickKeys);
        }
        if ($optionalPickKeys !== [] && $propsSource !== null) {
            $parts[] = new TypeScriptGeneric(new TypeScriptIdentifier('Partial'), [
                $this->pick($propsSource->typeName, $optionalPickKeys),
            ]);
        }
        if ($properties !== []) {
            $parts[] = new TypeScriptObject($properties);
        }

        return $parts;
    }

    /**
     * @param list<string> $keys
     */
    private function pick(string $typeName, array $keys): TypeScriptGeneric
    {
        return new TypeScriptGeneric(new TypeScriptIdentifier('Pick'), [
            new TypeScriptIdentifier($typeName),
            new TypeScriptUnion(array_map(
                static fn(string $key): TypeScriptLiteral => new TypeScriptLiteral($key),
                $keys,
            )),
        ]);
    }

    private function baseIdsObject(): TypeScriptObject
    {
        return new TypeScriptObject([
            new TypeScriptProperty('id', new TypeScriptString()),
            new TypeScriptProperty('ids', new TypeScriptGeneric(new TypeScriptIdentifier('Record'), [
                new TypeScriptString(),
                new TypeScriptString(),
            ])),
        ]);
    }

    private function targetFile(RootComponentLocation $location): string
    {
        $directory = $this->outputDir ?? $location->path;

        // realpath(), not the raw path: $location->path may be a vendor/<package>/... symlink path
        // (see GenerateHydrationTypesCommand::buildConfig()'s own comment) that needs to agree with
        // this file's own canonical coordinate system for cross-file relative imports to resolve
        // correctly. $outputDir (a CLI option, possibly not created yet) is left as-is when it
        // doesn't exist - nothing to normalize against.
        return (realpath($directory) ?: $directory) . '/' . $location->name . '.hydration.ts';
    }

    private function declareModuleFooter(string $primitiveName): string
    {
        return sprintf(
            "declare module 'fluid-primitives/client' {\n    interface HydrationPropsRegistry {\n        %s: %sHydrationProps;\n    }\n}\n",
            lcfirst($primitiveName),
            $primitiveName,
        );
    }
}
