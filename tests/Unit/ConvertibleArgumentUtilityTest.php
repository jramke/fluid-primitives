<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Domain\Dto\Positioning;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ConvertibleArgumentUtility;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

final class ConvertibleArgumentUtilityTest extends TestCase
{
    private function definition(string $type): ArgumentDefinition
    {
        return new ArgumentDefinition('positioning', $type, '', false);
    }

    #[Test]
    public function convertsAnArrayAndAScalarOnlyForAConvertibleClassInTheType(): void
    {
        $definition = $this->definition('array|string|' . Positioning::class);

        $fromArray = ConvertibleArgumentUtility::convert(['gutter' => 4], $definition);
        $fromString = ConvertibleArgumentUtility::convert('top', $definition);
        $this->assertInstanceOf(Positioning::class, $fromArray);
        $this->assertSame(['gutter' => 4], $fromArray->jsonSerialize());
        $this->assertSame(['placement' => 'top'], $fromString->jsonSerialize());

        // converting twice, and a value no class in the type is responsible for, change nothing
        $this->assertSame($fromArray, ConvertibleArgumentUtility::convert($fromArray, $definition));
        $this->assertSame(['a'], ConvertibleArgumentUtility::convert(['a'], $this->definition('array|string')));
        $this->assertNull(ConvertibleArgumentUtility::convert(null, $definition));

        $this->assertInstanceOf(ListCollection::class, ConvertibleArgumentUtility::convert(new \ArrayIterator([[
            'value' => 'a',
            'label' => 'A',
        ]]), $this->definition('array|' . ListCollection::class)));
    }
}
