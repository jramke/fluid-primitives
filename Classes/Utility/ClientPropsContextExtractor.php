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
        $reflection = new ReflectionClass($context);
        $props = [];

        foreach ($reflection->getMethods() as $method) {
            $clientProp = self::buildClientProp($method, $context);
            if ($clientProp !== false) {
                $props[$clientProp[0]] = $clientProp[1];
            }
        }

        return $props;
    }

    private static function buildClientProp(\ReflectionMethod $method, ComponentContextInterface $context): array|false
    {
        $attributes = $method->getAttributes(ExposeToClient::class);

        if ($attributes === []) {
            return false;
        }

        if (!$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $attribute = $attributes[0]->newInstance();

        // Reflection-invoking an arbitrary #[ExposeToClient] getter is inherently mixed - that's the
        // whole point of this extractor.
        // @mago-expect analysis:mixed-assignment
        $value = $method->invoke($context);

        if ($attribute->excludeIfNull && $value === null) {
            return false;
        }

        $name = $attribute->name ?? self::normalizeMethodName($method->getName());

        return [$name, EnumUtility::normalize($value)];
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst((string)preg_replace('/^(get|is|has)/', replacement: '', subject: $method));
    }
}
