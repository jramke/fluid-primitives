<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ExtbaseFormFieldNamer;
use Jramke\FluidPrimitives\Utility\ExtbasePersistedObjectResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

final class ExtbasePersistedObjectResolverTest extends TestCase
{
    #[Test]
    public function resolvesTheBoundObjectOnlyWhenItIsPersisted(): void
    {
        $resolver = new ExtbasePersistedObjectResolver(new ExtbaseFormFieldNamer());
        $persisted = $this->persisted(42);
        $boundObject = static fn(mixed $raw): ?object => $resolver->resolveForForm($raw, [])->boundObject;

        $this->assertSame($persisted, $boundObject($persisted));
        $this->assertNull($boundObject(new TestEntity()));
        $this->assertNull($boundObject('not an object'));
        $this->assertNull($boundObject(null));

        // A lazy relation is what a nested property returns: it is judged by the object it loads, and a
        // proxy whose record no longer exists loads as null.
        $this->assertSame($persisted, $boundObject($this->proxyLoading($persisted)));
        $this->assertNull($boundObject($this->proxyLoading(null)));
        $this->assertNull($boundObject($this->proxyLoading(new TestEntity())));
    }

    #[Test]
    public function derivesNestedPersistedObjectsFromFieldNamesInDotOrBracketNotationOneLevelDeep(): void
    {
        $nested = $this->persisted(7);
        $entity = $this->persisted(42);
        $entity->setNested($nested);

        $objects = (new ExtbasePersistedObjectResolver(new ExtbaseFormFieldNamer()))->resolveForForm($entity, [
            'field1' => ['name' => 'nested.name'],
            'field2' => ['name' => 'nested[email]'],
            'field3' => ['name' => 'ticketType'],
        ]);

        // `nested.name` and `nested[email]` both point at the same top-level "nested" property; "ticketType" has
        // one segment, so it is not a sub-object path at all.
        $this->assertSame(['nested' => $nested], $objects->nestedObjects);
    }

    #[Test]
    public function ignoresFieldsWhoseFirstSegmentIsNoPropertyOfTheBoundObject(): void
    {
        $nested = $this->persisted(7);
        $entity = $this->persisted(42);
        $entity->setNested($nested);

        // `address.city` is the field of a property the entity does not have, `people[0][firstName]` a field
        // in a FieldArray row; the nested one after them is the positive control that the loop went on
        $objects = (new ExtbasePersistedObjectResolver(new ExtbaseFormFieldNamer()))->resolveForForm($entity, [
            'field1' => ['name' => 'address.city'],
            'field2' => ['name' => 'people[0][firstName]'],
            'field3' => ['name' => 'nested.name'],
        ]);

        $this->assertSame(['nested' => $nested], $objects->nestedObjects);
    }

    #[Test]
    public function leavesOutNestedObjectsThatAreNotPersistedAndThoseOfAnObjectThatIsNot(): void
    {
        $resolver = new ExtbasePersistedObjectResolver(new ExtbaseFormFieldNamer());
        $fields = ['field1' => ['name' => 'nested.name']];

        $entity = $this->persisted(42);
        $entity->setNested(new TestEntity());
        $this->assertSame([], $resolver->resolveForForm($entity, $fields)->nestedObjects);

        $newEntity = new TestEntity();
        $newEntity->setNested($this->persisted(7));
        $this->assertSame([], $resolver->resolveForForm($newEntity, $fields)->nestedObjects);
        $this->assertSame([], $resolver->resolveForForm(null, $fields)->nestedObjects);
    }

    private function persisted(int $uid): TestEntity
    {
        $entity = new TestEntity();
        $entity->setTestUid($uid);

        return $entity;
    }

    private function proxyLoading(?object $realInstance): LazyLoadingProxy
    {
        $proxy = $this->createStub(LazyLoadingProxy::class);
        $proxy->method('_loadRealInstance')->willReturn($realInstance);

        return $proxy;
    }
}
