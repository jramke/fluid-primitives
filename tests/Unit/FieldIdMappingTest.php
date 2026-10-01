<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\FieldIdMapping;
use PHPUnit\Framework\Attributes\Test;

final class FieldIdMappingTest extends TestCase
{
    #[Test]
    public function getOverrideFieldIdKeyResolvesTheComponentSpecificPartNameOrNullIfUnmapped(): void
    {
        $this->assertSame('hiddenInput', FieldIdMapping::getOverrideFieldIdKey('switch', 'control'));
        $this->assertNull(FieldIdMapping::getOverrideFieldIdKey('unknown-component', 'control'));
    }

    #[Test]
    public function shouldSkipFieldIdsInheritanceWhenNestedInReturnsExclusionsForCheckbox(): void
    {
        $this->assertSame(['checkbox-group'], FieldIdMapping::shouldSkipFieldIdsInheritanceWhenNestedIn('checkbox'));
        $this->assertSame([], FieldIdMapping::shouldSkipFieldIdsInheritanceWhenNestedIn('switch'));
    }
}
