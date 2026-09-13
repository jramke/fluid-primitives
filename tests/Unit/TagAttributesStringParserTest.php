<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\TagAttributesStringParser;
use PHPUnit\Framework\Attributes\Test;

final class TagAttributesStringParserTest extends TestCase
{
    #[Test]
    public function parsesMixedKeyValueAndBooleanAttributes(): void
    {
        $result = TagAttributesStringParser::parse('class="test" disabled');
        $this->assertSame(['class' => 'test', 'disabled' => true], $result);
    }

    #[Test]
    public function handlesValuesWithEqualsSigns(): void
    {
        $result = TagAttributesStringParser::parse('data-equation="1+1=2"');
        $this->assertSame(['data-equation' => '1+1=2'], $result);
    }

    #[Test]
    public function returnsAnEmptyArrayForAnEmptyString(): void
    {
        $this->assertSame([], TagAttributesStringParser::parse(''));
    }
}
