<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Attributes\Ajax;
use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Service\ComponentCollectionService;
use ReflectionClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

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

            $ajaxProp = self::buildAjaxProp($method, $context);
            if ($ajaxProp !== false) {
                $props[$ajaxProp[0]] = $ajaxProp[1];
            }
        }

        return $props;
    }

    private static function buildAjaxProp(\ReflectionMethod $method, ComponentContextInterface $context): array|false
    {
        $attributes = $method->getAttributes(Ajax::class);

        if ($attributes === []) {
            return false;
        }

        if (!$method->isPublic()) {
            return false;
        }

        $normalizedMethodName = self::normalizeMethodName($method->getName());
        $propName = $normalizedMethodName . 'Url';

        $componentCollectionService = GeneralUtility::makeInstance(ComponentCollectionService::class);

        $namespaceIdentifier = $componentCollectionService->getViewHelperNamespaceIdentifierByCollectionClassName(
            $context->getComponentResolver()->getNamespace(),
        );

        $componentName = ComponentUtility::lowerCaseDashedToCamelCase(
            ComponentUtility::getComponentBaseNameFromContext($context->getRenderingContext()),
        );

        $params = [
            'id' => $context->getRequest()->getAttribute('routing')?->getPageId() ?? 1,
            'type' => 1783366837,
            'tx_fluidprimitives_ajaxdispatcher' => [
                'action' => 'dispatch',
                'component' => $namespaceIdentifier . ':' . $componentName,
                'method' => $method->getName(),
            ],
        ];

        $cacheHashCalculator = GeneralUtility::makeInstance(CacheHashCalculator::class);
        $cHashParams = $cacheHashCalculator->getRelevantParameters(http_build_query($params));
        $cHash = $cacheHashCalculator->calculateCacheHash($cHashParams);

        $queryString = http_build_query($params) . '&cHash=' . $cHash;
        $url = '/?' . $queryString;

        return [$propName, $url];
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

        if ($method->getAttributes(Ajax::class) !== []) {
            throw new \RuntimeException(
                sprintf(
                    'Method %s::%s cannot be annotated with both ExposeToClient and Ajax attributes.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                ),
                1783368546,
            );
        }

        $attribute = $attributes[0]->newInstance();

        $value = $method->invoke($context);

        if ($attribute->excludeIfNull && $value === null) {
            return false;
        }

        $name = $attribute->name ?? self::normalizeMethodName($method->getName());

        return [$name, EnumUtility::normalize($value)];
    }

    private static function normalizeMethodName(string $method): string
    {
        return lcfirst((string)preg_replace('/^(get|is|has)/', '', $method));
    }
}
