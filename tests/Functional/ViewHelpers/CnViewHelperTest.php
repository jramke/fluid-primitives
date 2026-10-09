<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\ViewHelperTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * `ui:cn` joins class names: a value (or the tag content), plus the keys of `when` whose condition holds. It does
 * not merge conflicting Tailwind utilities, that is not its job.
 */
final class CnViewHelperTest extends ViewHelperTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function values(): iterable
    {
        yield 'one class' => ['{ui:cn(value: \'my-class\')}', 'my-class'];
        yield 'surrounding and repeated whitespace' => [
            '{ui:cn(value: \'  class-one    class-two  \')}',
            'class-one class-two',
        ];
        yield 'a class string over several lines' => [
            "<ui:cn value=\"class-one\n    class-two\n    class-three\" />",
            'class-one class-two class-three',
        ];
        yield 'a repeated class' => ['{ui:cn(value: \'my-class my-class other my-class\')}', 'my-class other'];
        yield 'an empty value' => ['{ui:cn(value: \'\')}', ''];
        yield 'a whitespace-only value' => ['{ui:cn(value: \'   \')}', ''];
        yield 'the tag content' => ['<ui:cn>my-class</ui:cn>', 'my-class'];
        yield 'the tag content over several lines' => [
            "<ui:cn>\n    class-one\n    class-two\n</ui:cn>",
            'class-one class-two',
        ];
    }

    #[Test]
    #[DataProvider('values')]
    public function normalizesTheClassString(string $template, string $expected): void
    {
        $this->assertSame($expected, $this->renderTemplate($template));
    }

    #[Test]
    public function addsTheKeysOfWhenWhoseConditionHoldsAfterTheValue(): void
    {
        $this->assign('conditions', [
            'always-included',
            'also-always',
            'on' => true,
            'off' => false,
            'btn-primary btn-large' => true,
        ]);

        $this->assertSame(
            'base always-included also-always on btn-primary btn-large',
            $this->renderTemplate('{ui:cn(value: \'base\', when: conditions)}'),
        );
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function conditions(): iterable
    {
        yield 'true' => [true, true];
        yield 'false' => [false, false];
        yield 'a non-empty string' => ['foo', true];
        yield 'the string yes' => ['yes', true];
        yield 'the string false, in any case' => ['False', false];
        yield 'the string 0' => ['0', false];
        yield 'an empty string' => ['', false];
        yield 'the string no' => ['no', false];
        yield 'the string null' => ['null', false];
        yield 'the string undefined' => ['undefined', false];
        yield 'one' => [1, true];
        yield 'zero' => [0, false];
        yield 'null' => [null, false];
        yield 'a non-empty array' => [['x'], true];
        yield 'an empty array' => [[], false];
    }

    #[Test]
    #[DataProvider('conditions')]
    public function readsAConditionTheWayJavaScriptWouldForClsx(mixed $condition, bool $included): void
    {
        // the unconditional class is the positive control: the output is not empty for an unrelated reason
        $this->assign('conditions', ['always', 'my-class' => $condition]);

        $this->assertSame($included ? 'always my-class' : 'always', $this->renderTemplate('{ui:cn(when: conditions)}'));
    }

    #[Test]
    public function assignsTheResultToAVariableInsteadOfOutputtingIt(): void
    {
        // the variable is the only place the class shows up
        $this->assertSame('my-class', $this->renderTemplate('<ui:cn value="my-class" as="className" />{className}'));
    }
}
