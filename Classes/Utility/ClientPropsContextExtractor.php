<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use ReflectionClass;
use ReflectionMethod;

class ClientPropsContextExtractor
{
    public static function extract(ComponentContextInterface $context): array
    {
        $props = [];

        foreach (self::discover(new ReflectionClass($context)) as $entry) {
            // Reflection-invoking an arbitrary #[ExposeToClient] getter is inherently mixed -
            // that's the whole point of this extractor.
            // @mago-expect analysis:mixed-assignment
            $value = $entry['method']->invoke($context);

            if ($entry['attribute']->excludeIfNull && $value === null) {
                continue;
            }

            $props[$entry['name']] = EnumUtility::normalize($value);
        }

        return $props;
    }

    /**
     * The static half of what {@see extract()} does - every public, no-required-parameter method
     * carrying `#[ExposeToClient]`, with its already-normalized prop name, but without actually
     * invoking it. Used by `ui:generate-hydration-types` codegen, which only needs a method's own
     * signature (attribute + declared return type), never a real context instance to call it on.
     *
     * @return list<array{method: ReflectionMethod, attribute: ExposeToClient, name: string}>
     */
    public static function discover(ReflectionClass $reflection): array
    {
        $discovered = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(ExposeToClient::class);
            if ($attributes === [] || !$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $discovered[] = [
                'method' => $method,
                'attribute' => $attribute,
                'name' => $attribute->name ?? self::normalizeMethodName($method->getName()),
            ];
        }

        return $discovered;
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst((string)preg_replace('/^(get|is|has)/', replacement: '', subject: $method));
    }
}
