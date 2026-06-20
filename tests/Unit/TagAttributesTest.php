<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TagAttributesTest extends TestCase
{
    #[Test]
    public function rendersKeyValueAndBooleanAttributes(): void
    {
        $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
        $this->assertSame('class="test" id="myId" disabled', (string)$attrs);
    }

    #[Test]
    public function rendersAriaAttributesWithBooleanValuesAsStrings(): void
    {
        $attrs = new TagAttributes(['aria-expanded' => true, 'aria-hidden' => false]);
        $this->assertSame('aria-expanded="1" aria-hidden', (string)$attrs);
    }

    #[Test]
    public function escapesHtmlInValuesAndKeys(): void
    {
        $attrs = new TagAttributes([
            'data-value' => '<script>alert("xss")</script>',
            'data-<test>' => 'value',
        ]);
        $this->assertStringContainsString('&lt;script&gt;', (string)$attrs);
        $this->assertStringContainsString('data-&lt;test&gt;', (string)$attrs);
    }

    #[Test]
    public function encodesArraysAndObjectsAsJson(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $attrs = new TagAttributes(['data-items' => ['a', 'b'], 'data-obj' => $obj]);
        $this->assertStringContainsString('data-items="[&quot;a&quot;,&quot;b&quot;]"', (string)$attrs);
        $this->assertStringContainsString('data-obj="{&quot;name&quot;:&quot;test&quot;}"', (string)$attrs);
    }

    #[Test]
    public function filtersToSpecifiedKeysOnly(): void
    {
        $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
        $this->assertSame('class="test"', $attrs->renderWithOnly(['class']));
        $this->assertSame(['class' => 'test'], $attrs->renderWithOnly(['class'], true));
    }

    #[Test]
    public function excludesSpecifiedKeys(): void
    {
        $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
        $this->assertSame('class="test"', $attrs->renderWithSkip(['id', 'disabled']));
        $this->assertSame(['class' => 'test', 'disabled' => ''], $attrs->renderWithSkip(['id'], true));
    }

    #[Test]
    public function parsesMixedKeyValueAndBooleanAttributes(): void
    {
        $result = TagAttributes::stringToArray('class="test" disabled');
        $this->assertSame(['class' => 'test', 'disabled' => true], $result);
    }

    #[Test]
    public function handlesValuesWithEqualsSigns(): void
    {
        $result = TagAttributes::stringToArray('data-equation="1+1=2"');
        $this->assertSame(['data-equation' => '1+1=2'], $result);
    }
}
