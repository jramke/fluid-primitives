<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ExtbasePersistedObjectResolver;
use PHPUnit\Framework\Attributes\Test;

final class ExtbasePersistedObjectResolverTest extends TestCase
{
    #[Test]
    public function resolvesAPersistedObjectWithAUid(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);

        $this->assertSame($entity, (new ExtbasePersistedObjectResolver())->resolve($entity));
    }

    #[Test]
    public function returnsNullForANewUnpersistedObject(): void
    {
        $this->assertNull((new ExtbasePersistedObjectResolver())->resolve(new TestEntity()));
    }

    #[Test]
    public function returnsNullForANonDomainObjectValue(): void
    {
        $this->assertNull((new ExtbasePersistedObjectResolver())->resolve('not an object'));
        $this->assertNull((new ExtbasePersistedObjectResolver())->resolve(null));
    }

    #[Test]
    public function derivesNestedPersistedObjectsFromDottedFieldNamesOneLevelDeep(): void
    {
        $nested = new TestEntity();
        $nested->setTestUid(7);

        $entity = new TestEntity();
        $entity->setTestUid(42);
        $entity->setNested($nested);

        $resolver = new ExtbasePersistedObjectResolver();
        $nestedObjects = $resolver->getNestedPersistedObjects($entity, [
            'field1' => ['name' => 'nested.name'],
            'field2' => ['name' => 'nested.email'],
            'field3' => ['name' => 'ticketType'],
        ]);

        // `nested.name` and `nested.email` both point at the same top-level "nested" property, so
        // it's only resolved once; "ticketType" has no dot, so it's not a sub-object path at all.
        $this->assertSame(['nested' => $nested], $nestedObjects);
    }

    #[Test]
    public function omitsNestedObjectsThatAreThemselvesUnpersisted(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $entity->setNested(new TestEntity());

        $resolver = new ExtbasePersistedObjectResolver();
        $nestedObjects = $resolver->getNestedPersistedObjects($entity, [
            'field1' => ['name' => 'nested.name'],
        ]);

        $this->assertSame([], $nestedObjects);
    }

    #[Test]
    public function returnsNoNestedObjectsWhenTheRootIsNotPersisted(): void
    {
        $resolver = new ExtbasePersistedObjectResolver();

        $this->assertSame([], $resolver->getNestedPersistedObjects(null, ['field1' => ['name' => 'nested.name']]));
    }
}
