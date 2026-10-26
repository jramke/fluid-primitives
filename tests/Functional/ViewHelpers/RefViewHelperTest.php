<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class RefViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function attachesToTheComponentNamedByContextInsideHandAuthoredSlotContent(): void
    {
        // Plain slot content, several layers deep (root -> content -> empty), is evaluated where the component around
        // it is not known: `context` names the ancestor to attach to.
        $html = $this->renderTemplate('
            <primitives:combobox.root rootId="my-combobox" collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>
                        <span {ui:ref(name: \'statusText\', context: \'combobox\')}>Loading…</span>
                    </primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);

        $this->assertSame(
            'my-combobox',
            $this->elementWith($html, 'data-combobox-status-text')->get('data-combobox-status-text'),
        );
    }

    #[Test]
    public function marksAnElementOfAMultiWordComponentWithTheKebabCaseNameTheClientQueriesBy(): void
    {
        // The slot content of an item template is evaluated against the rendering context of the call site, where the
        // component around it is not known, so the element names it. `fileUpload` becomes `file-upload` in the marker.
        $html = $this->renderTemplate('
            <primitives:fileUpload.root rootId="my-upload">
                <primitives:fileUpload.itemTemplate>
                    <primitives:fileUpload.item>
                        <div {ui:ref(name: \'diagField\', context: \'fileUpload\')}>diag</div>
                    </primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');

        $this->assertSame(
            'my-upload',
            $this->elementWith($html, 'data-file-upload-diag-field')->get('data-file-upload-diag-field'),
        );
    }

    #[Test]
    public function throwsForAContextThatIsNotActive(): void
    {
        $this->assertRenderThrows(
            MissingContextException::class,
            'ui:ref could not find an active "select" component to attach to.',
            function (): void {
                $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>
                        <span {ui:ref(name: \'statusText\', context: \'select\')}>Loading…</span>
                    </primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);
            },
        );
    }
}
