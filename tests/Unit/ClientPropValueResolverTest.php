<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\ClientPropConverterRegistry;
use Jramke\FluidPrimitives\Service\Component\ClientPropValueResolver;
use Jramke\FluidPrimitives\Tests\Fixtures\ClientTypeAwareOnlyObject;
use Jramke\FluidPrimitives\Tests\Fixtures\ConvertibleLegacyObject;
use Jramke\FluidPrimitives\Tests\Fixtures\LegacyObjectConverter;
use Jramke\FluidPrimitives\Tests\Fixtures\NonConvertibleObject;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

final class ClientPropValueResolverTest extends TestCase
{
    #[Test]
    public function resolveClientValuePassesThroughAClientTypeAwareValueEvenThoughItIsNotJsonSerializable(): void
    {
        $resolver = new ClientPropValueResolver(new ClientPropConverterRegistry([]));
        $value = new ClientTypeAwareOnlyObject();

        $this->assertSame($value, $resolver->resolveClientValue($value, 'prop', 'component', null));
    }

    #[Test]
    public function resolveClientValueConvertsViaAMatchingRegisteredConverter(): void
    {
        $resolver = new ClientPropValueResolver(new ClientPropConverterRegistry([new LegacyObjectConverter()]));
        $definition = new ArgumentDefinition('prop', ConvertibleLegacyObject::class, '', false);

        $result = $resolver->resolveClientValue(new ConvertibleLegacyObject('hello'), 'prop', 'component', $definition);

        $this->assertSame(['legacyValue' => 'hello'], $result);
    }

    #[Test]
    public function resolveClientValueThrowsForAnObjectThatIsNeitherClientTypeAwareConvertibleNorJsonSerializable(): void
    {
        $resolver = new ClientPropValueResolver(new ClientPropConverterRegistry([]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('myProp');
        $this->expectExceptionMessage('myComponent');
        $this->expectExceptionMessage(NonConvertibleObject::class);

        $resolver->resolveClientValue(new NonConvertibleObject(), 'myProp', 'myComponent', null);
    }

    #[Test]
    public function assertRequiredPropNotNullOnlyThrowsForARequiredDefinitionResolvingToNull(): void
    {
        $resolver = new ClientPropValueResolver(new ClientPropConverterRegistry([]));
        $required = new ArgumentDefinition('prop', 'string', '', true);
        $optional = new ArgumentDefinition('prop', 'string', '', false);

        // None of these resolve to null while required, so none should throw.
        $resolver->assertRequiredPropNotNull('value', 'prop', 'component', $required);
        $resolver->assertRequiredPropNotNull(null, 'prop', 'component', $optional);
        $resolver->assertRequiredPropNotNull(null, 'prop', 'component', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prop');
        $resolver->assertRequiredPropNotNull(null, 'prop', 'component', $required);
    }
}
