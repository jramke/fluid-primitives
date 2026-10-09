<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class RefViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function attachesToTheComponentNamedByContextInsideHandAuthoredSlotContent(): void
    {
        // Unlike the item template of a component, this span is neither a component's own template body nor wrapped in
        // ui:template - it's plain slot content, several layers deep (root -> content -> empty). `context` targets
        // the "combobox" ancestor explicitly instead of relying on whichever component happens to be ambiently active.
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
    public function stillAttachesByContextAfterAnotherComponentRenderedEarlierInTheSameSlot(): void
    {
        // Some part of the render of another primitive (here the preview) leaves the *ambient* `component`/`context`
        // Fluid variables unset for whatever renders next in the same slot, although the enclosing component is still
        // on the stack of contexts that the `context` argument reads - which is why the documentation of the file
        // upload tells consumer-authored elements to name it.
        $html = $this->renderTemplate('
            <primitives:fileUpload.root rootId="my-upload">
                <primitives:fileUpload.itemTemplate>
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemPreview match=".*">
                            <primitives:fileUpload.itemPreviewFallback />
                        </primitives:fileUpload.itemPreview>
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
        $this->expectExceptionMessage('ui:ref could not find an active "select" component to attach to.');

        $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <primitives:combobox.content>
                    <primitives:combobox.empty>
                        <span {ui:ref(name: \'statusText\', context: \'select\')}>Loading…</span>
                    </primitives:combobox.empty>
                </primitives:combobox.content>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);
    }
}
