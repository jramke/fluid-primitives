<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Command;

use Jramke\FluidPrimitives\Annotations\ClientArgumentAnnotation;
use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use ReflectionClass;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Collects every client-facing {@see HydrationPropDefinition} for one root component - both its
 * `ui:prop client="{true}"` declarations and its Context class's `#[ExposeToClient]` methods -
 * split out from {@see GenerateHydrationTypesCommand} purely to keep that class's own complexity
 * down.
 */
final readonly class HydrationComponentPropsCollector
{
    public function __construct(
        private HydrationPropsSourceResolver $propsSourceResolver,
        private HydrationValueTypeResolver $valueTypeResolver,
    ) {}

    /**
     * @return array{propDefinitions: list<HydrationPropDefinition>, propsSource: ?HydrationPropsTypeSource}
     */
    public function collect(ComponentCollectionInterface $collection, RootComponentLocation $location): array
    {
        $clientBaseName = lcfirst($location->name);
        $propsSource = $this->resolvePropsSource($location);

        /** @var array<string, HydrationPropDefinition> $props */
        $props = [];

        foreach ($collection
            ->getComponentDefinition($location->viewHelperName)
            ->getArgumentDefinitions() as $name => $definition) {
            if (!$this->isClientMarked($definition)) {
                continue;
            }

            $props[$name] = $this->valueTypeResolver->resolveForArgument(
                $clientBaseName,
                $name,
                $definition,
                $propsSource,
            );
        }

        // Context-derived props are resolved second and overwrite a same-named ui:prop entry -
        // mirroring ComponentHydrationCollector's own `[...$propsMarkedForClientValues,
        // ...$clientPropsFromContext]` spread order at runtime (e.g. SelectContext::getTranslations()
        // overriding the raw `translations` ui:prop).
        $contextClass = ComponentUtility::getContextClassNameFromViewHelperName(
            $location->viewHelperName,
            $collection->getContextNamespaces(),
        );
        if (is_subclass_of($contextClass, AbstractComponentContext::class)) {
            foreach (ClientPropsContextExtractor::discover(new ReflectionClass($contextClass)) as $entry) {
                $props[$entry['name']] = $this->valueTypeResolver->resolveForContextMethod(
                    $clientBaseName,
                    $entry['name'],
                    $entry['method'],
                    $entry['attribute'],
                    $propsSource,
                );
            }
        }

        return ['propDefinitions' => array_values($props), 'propsSource' => $propsSource];
    }

    private function resolvePropsSource(RootComponentLocation $location): ?HydrationPropsTypeSource
    {
        $classFile = $location->path . '/' . $location->name . '.ts';
        if (!is_file($classFile)) {
            return null;
        }

        return $this->propsSourceResolver->resolve(
            (string)file_get_contents($classFile),
            $classFile,
            $location->name . 'Props',
        );
    }

    private function isClientMarked(ArgumentDefinition $definition): bool
    {
        foreach ($definition->getAnnotations() as $annotation) {
            if ($annotation instanceof ClientArgumentAnnotation) {
                return true;
            }
        }

        return false;
    }
}
