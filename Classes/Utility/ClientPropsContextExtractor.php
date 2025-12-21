<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use ReflectionClass;

class ClientPropsContextExtractor
{
    public static function extract(object $context): array
    {
        $reflection = new ReflectionClass($context);
        $props = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(ExposeToClient::class);

            if ($attributes === []) {
                continue;
            }

            if (!$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            $name = $attribute->name
                ?? self::normalizeMethodName($method->getName());

            $props[$name] = $method->invoke($context);
        }

        return $props;
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst(preg_replace('/^(get|is|has)/', '', $method));
    }
}
