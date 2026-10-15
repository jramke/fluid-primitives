<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\GlobalContextInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Core\Environment;

class ClientPropsContextExtractor
{
    /**
     * The values of every `#[ExposeToClient]` getter of `$context`: a component context's per-instance
     * props, or the request globals of the global context, where `name` is a dot path
     * (`'lightbox.icons'`) that becomes nested objects.
     *
     * @return array<string, mixed>
     */
    public static function extract(object $context): array
    {
        $props = [];
        $isGlobalContext = $context instanceof GlobalContextInterface;

        foreach (self::discover($context::class) as $name => $discovered) {
            // Reflection-invoking an arbitrary #[ExposeToClient] getter is inherently mixed - that's
            // the whole point of this extractor.
            // @mago-expect analysis:mixed-assignment
            $value = $discovered['method']->invoke($context);

            if ($discovered['excludeIfNull'] && $value === null) {
                continue;
            }

            // @mago-expect analysis:mixed-assignment
            $value = EnumUtility::normalize($value);
            if (!$isGlobalContext) {
                $props[$name] = $value;
                continue;
            }

            if (!self::isSerializable($value)) {
                if (self::isDevelopment()) {
                    throw new \RuntimeException(
                        sprintf(
                            '%s::%s() is exposed to the client but its value cannot be encoded as JSON.',
                            $context::class,
                            $discovered['method']->getName(),
                        ),
                        1_788_200_007,
                    );
                }
                continue;
            }

            $props = self::withValueAtPath($props, explode('.', $name), $value);
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

    /**
     * @param array<string, mixed> $target
     * @param list<string> $segments
     * @return array<string, mixed>
     */
    private static function withValueAtPath(array $target, array $segments, mixed $value): array
    {
        $key = (string)array_shift($segments);
        if ($segments === []) {
            $target[$key] = $value;

            return $target;
        }

        // @mago-expect analysis:mixed-assignment
        $child = $target[$key] ?? null;
        /** @var array<string, mixed> $child */
        $child = is_array($child) ? $child : [];
        $target[$key] = self::withValueAtPath($child, $segments, $value);

        return $target;
    }

    private static function isSerializable(mixed $value): bool
    {
        return json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR) !== false && json_last_error() === JSON_ERROR_NONE;
    }

    private static function isDevelopment(): bool
    {
        try {
            return Environment::getContext()->isDevelopment();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst((string)preg_replace('/^(get|is|has)/', replacement: '', subject: $method));
    }
}
