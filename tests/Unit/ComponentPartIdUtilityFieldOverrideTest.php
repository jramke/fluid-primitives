<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentPartIdUtilityFieldOverrideTest extends TestCase
{
    #[Test]
    public function getOverrideFieldIdKeyResolvesTheComponentSpecificPartNameOrNullIfUnmapped(): void
    {
        $this->assertSame('hiddenInput', ComponentPartIdUtility::getOverrideFieldIdKey('switch', 'control'));
        $this->assertNull(ComponentPartIdUtility::getOverrideFieldIdKey('unknown-component', 'control'));
    }

    #[Test]
    public function shouldSkipFieldIdsInheritanceWhenNestedInReturnsExclusionsForCheckbox(): void
    {
        $this->assertSame(['checkbox-group'], ComponentPartIdUtility::shouldSkipFieldIdsInheritanceWhenNestedIn('checkbox'));
        $this->assertSame([], ComponentPartIdUtility::shouldSkipFieldIdsInheritanceWhenNestedIn('switch'));
    }
}
