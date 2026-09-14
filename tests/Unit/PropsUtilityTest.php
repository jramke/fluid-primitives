<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

final class PropsUtilityTest extends TestCase
{
    #[Test]
    public function keepsAsChildAndClassButStripsPurelyContextualPropsWhenCleaningNonForwardableProps(): void
    {
        $props = [
            'asChild' => new ArgumentDefinition('asChild', 'boolean', '', false, null),
            'class' => new ArgumentDefinition('class', 'string', '', false, null),
            'rootId' => new ArgumentDefinition('rootId', 'string', '', false, null),
            'context' => new ArgumentDefinition('context', 'mixed', '', false, null),
            'component' => new ArgumentDefinition('component', 'mixed', '', false, null),
            'settings' => new ArgumentDefinition('settings', 'array', '', false, null),
            'variant' => new ArgumentDefinition('variant', 'string', '', false, null),
        ];

        $result = PropsUtility::cleanupNonForwardableProps($props);

        // asChild/class must survive here so a ui:useProps + spreadProps wrapper keeps inheriting
        // them from whatever it imports from - rootId/context/component/settings are tied to the
        // specific render they came from and must not leak into an importer's own signature.
        $this->assertSame(['asChild', 'class', 'variant'], array_keys($result));
    }
}
