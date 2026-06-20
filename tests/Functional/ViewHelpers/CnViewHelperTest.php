<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\ViewHelperTestCase;
use PHPUnit\Framework\Attributes\Test;

// @mago-expect lint:too-many-methods
final class CnViewHelperTest extends ViewHelperTestCase
{
    #[Test]
    public function rendersASimpleClassString(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'my-class\')}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function rendersMultipleSpaceSeparatedClasses(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'class-one class-two class-three\')}');
        $this->assertSame('class-one class-two class-three', $result);
    }

    #[Test]
    public function trimsWhitespaceFromClassNames(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'  my-class  \')}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function normalizesMultipleSpacesBetweenClasses(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'class-one    class-two\')}');
        $this->assertSame('class-one class-two', $result);
    }

    #[Test]
    public function handlesMultilineClassStrings(): void
    {
        $result = $this->renderTemplate("<ui:cn value=\"class-one\n    class-two\n    class-three\" />");
        $this->assertSame('class-one class-two class-three', $result);
    }

    #[Test]
    public function returnsEmptyStringForEmptyValue(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'\')}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function returnsEmptyStringForWhitespaceOnlyValue(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'   \')}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function deduplicatesRepeatedClasses(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'my-class my-class my-class\')}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function includesClassWhenConditionIsTrue(): void
    {
        $this->assign('conditions', ['conditional-class' => true]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('conditional-class', $result);
    }

    #[Test]
    public function excludesClassWhenConditionIsFalse(): void
    {
        $this->assign('conditions', ['conditional-class' => false]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function handlesMixedTrueFalseConditions(): void
    {
        $this->assign('conditions', [
            'included' => true,
            'excluded' => false,
            'also-included' => true,
        ]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('included also-included', $result);
    }

    #[Test]
    public function combinesValueAndWhenArguments(): void
    {
        $this->assign('conditions', ['conditional' => true]);
        $result = $this->renderTemplate('{ui:cn(value: \'base-class\', when: conditions)}');
        $this->assertSame('base-class conditional', $result);
    }

    #[Test]
    public function supportsMultipleClassesPerCondition(): void
    {
        $this->assign('conditions', ['class-one class-two' => true]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('class-one class-two', $result);
    }

    #[Test]
    public function handlesIndexedArrayValuesAsUnconditionalClasses(): void
    {
        $this->assign('conditions', ['always-included', 'also-always']);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('always-included also-always', $result);
    }

    #[Test]
    public function handlesMixedIndexedAndAssociativeArrays(): void
    {
        $this->assign('conditions', [
            'unconditional',
            'conditional' => true,
            'excluded' => false,
        ]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('unconditional conditional', $result);
    }

    #[Test]
    public function treatsStringFalseAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => 'false']);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsStringZeroAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => '0']);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsEmptyStringAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => '']);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsNonEmptyStringAsTruthy(): void
    {
        $this->assign('conditions', ['my-class' => 'yes']);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function treatsNumericZeroAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => 0]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsNumericOneAsTruthy(): void
    {
        $this->assign('conditions', ['my-class' => 1]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function treatsNullAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => null]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsEmptyArrayAsFalsy(): void
    {
        $this->assign('conditions', ['my-class' => []]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function treatsNonEmptyArrayAsTruthy(): void
    {
        $this->assign('conditions', ['my-class' => ['item']]);
        $result = $this->renderTemplate('{ui:cn(when: conditions)}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function assignsResultToVariableInsteadOfOutputting(): void
    {
        $result = $this->renderTemplate('<ui:cn value="my-class" as="className" />{className}');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function returnsEmptyStringWhenUsingAsArgument(): void
    {
        $result = $this->renderTemplate('{ui:cn(value: \'my-class\', as: \'className\')}');
        $this->assertSame('', $result);
    }

    #[Test]
    public function rendersClassFromTagContent(): void
    {
        $result = $this->renderTemplate('<ui:cn>my-class</ui:cn>');
        $this->assertSame('my-class', $result);
    }

    #[Test]
    public function handlesMultilineContentInTagStyle(): void
    {
        $result = $this->renderTemplate('<ui:cn>
                class-one
                class-two
                class-three
            </ui:cn>');
        $this->assertSame('class-one class-two class-three', $result);
    }
}
