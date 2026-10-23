<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function rendersAriaAttributesWithBooleanValuesAsTrueAndFalse(): void
    {
        $attrs = new TagAttributes(['aria-expanded' => true, 'aria-hidden' => false]);
        $this->assertSame('aria-expanded="true" aria-hidden="false"', (string)$attrs);
    }

    #[Test]
    public function escapesHtmlInValues(): void
    {
        $attrs = new TagAttributes(['data-value' => '<script>alert("xss")</script>']);

        $this->assertSame('data-value="&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"', (string)$attrs);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function namesThatCarryMoreThanAName(): iterable
    {
        yield 'a space' => ['data-x onfocus=alert(1) autofocus a'];
        yield 'an equals sign' => ['a=b'];
        yield 'a slash' => ['a/onload=alert(1)'];
        yield 'a quote' => ['a"b'];
        yield 'an angle bracket' => ['data-<test>'];
        yield 'an ampersand' => ['a&b'];
    }

    #[Test]
    #[DataProvider('namesThatCarryMoreThanAName')]
    public function rejectsAnAttributeNameThatWouldRenderMoreAttributes(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attribute name provided');

        new TagAttributes([$name => 'v']);
    }

    #[Test]
    public function acceptsTheAttributeNamesFrameworksUse(): void
    {
        $attrs = new TagAttributes([
            '@click.prevent' => 'go',
            ':class' => 'a',
            'x-on:keydown.esc' => 'b',
            'data-a_b.c' => 'c',
        ]);

        $this->assertSame('@click.prevent="go" :class="a" x-on:keydown.esc="b" data-a_b.c="c"', (string)$attrs);
    }

    #[Test]
    public function leavesOutAUrlThatRunsScriptWhereABrowserFollowsIt(): void
    {
        $attrs = new TagAttributes([
            'href' => " \tJava\nScript:alert(1)",
            'action' => 'vbscript:x',
            'formaction' => 'data:text/html,<b>',
            'src' => 'data:image/png;base64,AAAA',
            'title' => 'javascript:not a url',
        ]);

        // the image is data on purpose and a title is no URL
        $this->assertSame('src="data:image/png;base64,AAAA" title="javascript:not a url"', (string)$attrs);
        $this->assertSame(
            ['src' => 'data:image/png;base64,AAAA', 'title' => 'javascript:not a url'],
            $attrs->renderAsArray(),
        );
    }

    #[Test]
    public function skipsAndKeepsAttributesWhateverTheirCase(): void
    {
        $attrs = new TagAttributes(['hidden' => true, 'Type' => 'text', 'id' => 'a']);

        $this->assertSame('id="a"', $attrs->renderWithSkip(['HIDDEN', 'type']));
        $this->assertSame('hidden Type="text"', $attrs->renderWithOnly(['Hidden', 'TYPE']));
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
    public function rendersZeroValuesLiterallyInsteadOfAsBooleanAttributes(): void
    {
        // Regression test: a "0" value (e.g. a recurring field's row index)
        // must not be treated like an empty/falsy boolean attribute, or the
        // value is silently dropped from the rendered attribute.
        $attrs = new TagAttributes(['data-index' => 0]);
        $this->assertSame('data-index="0"', (string)$attrs);

        $attrs = new TagAttributes(['data-index' => '0']);
        $this->assertSame('data-index="0"', (string)$attrs);
    }
}
