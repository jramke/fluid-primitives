<?php

declare(strict_types=1);

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

namespace Jramke\FluidPrimitives\Component;

use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Core\ViewHelper\TemplateStructurePlaceholderViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;

// TODO Fluid will move this into the Templateparser in the future, so we then need to adjust this.
/**
 * This extends the current Resolver to also include ui:prop viewhelpers, as we extend the cores f:argument with them.
 * This resolver is used for a first parsing of a component template.
 */
final class TemplateStructureViewHelperResolver extends ViewHelperResolver
{
    private const STRUCTURE_VIEWHELPERS = [
        'layout',
        'section',
        'argument',
        'prop',
        'slot',
        'useProps'
    ];

    public function isNamespaceValid(string $namespaceIdentifier): bool
    {
        return $namespaceIdentifier === 'f' || $namespaceIdentifier === 'ui';
    }

    public function isNamespaceIgnored(string $namespaceIdentifier): bool
    {
        return $namespaceIdentifier !== 'f' && $namespaceIdentifier !== 'ui';
    }

    public function resolveViewHelperClassName(string $namespaceIdentifier, string $methodIdentifier): string
    {
        if (($namespaceIdentifier === 'f' || $namespaceIdentifier === 'ui') && in_array($methodIdentifier, self::STRUCTURE_VIEWHELPERS)) {
            return parent::resolveViewHelperClassName($namespaceIdentifier, $methodIdentifier);
        }
        return TemplateStructurePlaceholderViewHelper::class;
    }

    public function getResponsibleDelegate(string $namespaceIdentifier, string $methodIdentifier): ?ViewHelperResolverDelegateInterface
    {
        if (($namespaceIdentifier === 'f' || $namespaceIdentifier === 'ui') && in_array($methodIdentifier, self::STRUCTURE_VIEWHELPERS)) {
            return parent::getResponsibleDelegate($namespaceIdentifier, $methodIdentifier);
        }
        return null;
    }
}
