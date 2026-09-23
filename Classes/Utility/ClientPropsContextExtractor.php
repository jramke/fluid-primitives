<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use ReflectionClass;

class ClientPropsContextExtractor
{
    public static function extract(ComponentContextInterface $context): array
    {
        $props = [];

        foreach (self::discover($context::class) as $name => $discovered) {
            // Reflection-invoking an arbitrary #[ExposeToClient] getter is inherently mixed - that's
            // the whole point of this extractor.
            // @mago-expect analysis:mixed-assignment
            $value = $discovered['method']->invoke($context);

            if ($discovered['excludeIfNull'] && $value === null) {
                continue;
            }

            $props[$name] = EnumUtility::normalize($value);
        }

        return $props;
    }

    /**
     * Static counterpart to {@see extract()}: which methods are exposed and their declared return
     * types, without a live Context instance to invoke them on (e.g. static TypeScript generation).
     * Reflects the same #[ExposeToClient] methods {@see extract()} does, but never invokes them.
     *
     * @return array<string, array{method: \ReflectionMethod, excludeIfNull: bool}>
     */
    public static function discover(string $contextClass): array
    {
        $reflection = new ReflectionClass($contextClass);
        $props = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(ExposeToClient::class);

            if ($attributes === [] || !$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $name = $attribute->name ?? self::normalizeMethodName($method->getName());

            $props[$name] = ['method' => $method, 'excludeIfNull' => $attribute->excludeIfNull];
        }

        return $props;
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst((string)preg_replace('/^(get|is|has)/', replacement: '', subject: $method));
    }
}
