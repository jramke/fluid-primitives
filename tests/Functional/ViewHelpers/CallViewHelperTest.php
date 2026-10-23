<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\ViewHelperTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CallViewHelperTest extends ViewHelperTestCase
{
    #[Test]
    public function escapesTheTextAMethodReturnsLikeAnyOtherVariable(): void
    {
        $this->assign('post', new class {
            public function excerpt(int $words): string
            {
                return '<img src=x onerror=alert(' . $words . ')> "quoted"';
            }
        });

        $this->assertSame(
            '<p>&lt;img src=x onerror=alert(5)&gt; &quot;quoted&quot;</p>',
            $this->renderTemplate('<p>{post -> ui:call(method: \'excerpt\', arguments: {0: 5})}</p>'),
        );
        $this->assertSame(
            '<a title="&lt;img src=x onerror=alert(5)&gt; &quot;quoted&quot;">',
            $this->renderTemplate('<a title="{post -> ui:call(method: \'excerpt\', arguments: {0: 5})}">'),
        );
    }

    #[Test]
    public function returnsAnObjectUntouchedForAFurtherAccess(): void
    {
        $this->assign('context', new class {
            public function itemState(string $value): object
            {
                return (object)['selected' => true, 'value' => $value];
            }
        });

        $this->assertSame('yes &lt;b&gt;', $this->renderTemplate(
            '<f:variable name="state" value="{context -> ui:call(method: \'itemState\', arguments: {0: \'<b>\'})}" />' .
            '{f:if(condition: state.selected, then: \'yes \')}{state.value}',
        ));
    }
}
