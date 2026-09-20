<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NestedComponentRegistryTest extends TestCase
{
    #[Test]
    public function recordsNestedComponentsAgainstOpenTrackingScopesAndTheAdditionalKey(): void
    {
        $registry = new NestedComponentRegistry();

        $registry->pushTrackingScope('field-array:«f0»:itemTemplate');
        $registry->recordNestedComponent('field', '«f1»');
        $registry->recordNestedComponent('input', '«f2»', 'field-array:«f0»:item:0');
        $registry->popTrackingScope();

        // Recorded after the scope closed - must not appear anywhere.
        $registry->recordNestedComponent('field', '«f3»');

        $byScope = $registry->getNestedComponentsByScope();

        $this->assertSame(
            [['name' => 'field', 'id' => '«f1»'], ['name' => 'input', 'id' => '«f2»']],
            $byScope['field-array:«f0»:itemTemplate'],
        );
        $this->assertSame([['name' => 'input', 'id' => '«f2»']], $byScope['field-array:«f0»:item:0']);
        $this->assertArrayNotHasKey('field-array:«f3»', $byScope);
    }

    #[Test]
    public function clearResetsBothTheScopeStackAndRecordedComponents(): void
    {
        $registry = new NestedComponentRegistry();

        $registry->pushTrackingScope('field-array:«f0»:itemTemplate');
        $registry->recordNestedComponent('field', '«f1»');
        $registry->clear();

        // If clear() left a stale scope on the stack, this would silently resurrect it instead of
        // being the no-op a component registered with no scope active should be.
        $registry->recordNestedComponent('field', '«f2»');

        $this->assertSame([], $registry->getNestedComponentsByScope());
    }
}
