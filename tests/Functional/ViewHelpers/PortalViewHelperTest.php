<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\PageRenderer;

final class PortalViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function rendersDefaultNamedPortalContentViaPageRendererFooterWithoutAContainer(): void
    {
        $html = $this->renderTemplate('
            <ui:portal>
                <div data-testid="portaled">Portaled content</div>
            </ui:portal>
        ');

        // No ui:portalContainer was used, yet the portal still renders nothing in place - it went
        // straight into PageRenderer's footer instead.
        $this->assertSame('', trim($html));
        $this->assertStringContainsString('data-testid="portaled"', $this->readFooterData());
    }

    #[Test]
    public function rendersANamedPortalOnlyWhereItsContainerIsAndNeverInThePageFooter(): void
    {
        $html = $this->renderTemplate('
            <ui:portal name="sidebar">
                <div data-testid="sidebar-portaled">Sidebar content</div>
            </ui:portal>
            <div id="target"><ui:portalContainer name="sidebar" /></div>
            <div id="again"><ui:portalContainer name="sidebar" /></div>
        ');

        $this->assertStringContainsString(
            '<div id="target"><template data-portal-container="sidebar"></template><div data-testid="sidebar-portaled">',
            $html,
        );
        // a container empties its portal: another one of the same name would otherwise repeat the markup
        $this->assertStringContainsString(
            '<div id="again"><template data-portal-container="sidebar"></template></div>',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'data-testid="sidebar-portaled"'));
        // only the default portal goes to the footer, which core renders at the end of <body> on its own
        $this->assertStringNotContainsString('data-testid="sidebar-portaled"', $this->readFooterData());
    }

    #[Test]
    public function rendersInPlaceInsideAUiTemplateStencilInsteadOfPortaling(): void
    {
        $collection = new ListCollection([]);

        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <ui:template name="itemTemplate" context="combobox">
                    <ui:portal>
                        <div data-testid="stencil-portaled">Portaled content</div>
                    </ui:portal>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => $collection]);

        // The stencil's own <template> tag still wraps it, proving it stayed part of the clonable
        // content instead of leaking out to the footer.
        $this->assertMatchesRegularExpression(
            '/<template data-combobox-item-template="[^"]*">.*data-testid="stencil-portaled".*<\/template>/s',
            $html,
        );
        $this->assertStringNotContainsString('data-testid="stencil-portaled"', $this->readFooterData());
        // what a clone of the stencil moves to the end of the body
        $wrapper = $this->elementWith($html, 'data-portal');
        $this->assertSame('default', $wrapper->get('data-portal'));
    }

    #[Test]
    public function leavesADisabledPortalInAStencilInPlaceWithoutAWrapperAndNamesTheWrapperOfANamedOne(): void
    {
        $html = $this->renderTemplate('
            <primitives:combobox.root collection="{collection}">
                <ui:template name="itemTemplate" context="combobox">
                    <ui:portal disabled="{true}"><b data-testid="stays">In place</b></ui:portal>
                    <ui:portal name="sidebar"><i data-testid="moves">Moves</i></ui:portal>
                </ui:template>
            </primitives:combobox.root>
        ', ['collection' => new ListCollection([])]);

        // the named one is the positive control that wrappers are rendered at all
        $this->assertSame('sidebar', $this->elementWith($html, 'data-portal')->get('data-portal'));
        $this->assertStringContainsString('<b data-testid="stays">In place</b>', $html);
        $this->assertStringNotContainsString('data-portal="default"', $html);
    }

    #[Test]
    public function rendersInPlaceInsideTheStencilOfAFieldArrayRowOnlyForTheRowsTheClientClones(): void
    {
        // A popover nested in the row: its portal sits in the template of the popover, not in the one of the stencil's
        // owner, so only the open tracking scope of the stencil tells it apart from the rendered row below.
        $popover = '
            <primitives:popover.root>
                <primitives:popover.trigger>Open</primitives:popover.trigger>
                <ui:portal>
                    <primitives:popover.positioner>
                        <primitives:popover.content>%s</primitives:popover.content>
                    </primitives:popover.positioner>
                </ui:portal>
            </primitives:popover.root>
        ';

        $html = $this->renderTemplate(
            '
            <primitives:fieldArray.root name="people" itemCount="1">
                <primitives:fieldArray.itemTemplate>
                    <primitives:fieldArray.item>' .
            sprintf($popover, 'Stencil popover') .
            '</primitives:fieldArray.item>
                </primitives:fieldArray.itemTemplate>
                <primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.item index="0">' .
            sprintf($popover, 'Row popover') .
            '</primitives:fieldArray.item>
                </primitives:fieldArray.itemGroup>
            </primitives:fieldArray.root>
        ',
        );

        $this->assertMatchesRegularExpression('/<template [^>]*>.*Stencil popover.*<\/template>/s', $html);
        $footer = $this->readFooterData();
        $this->assertStringNotContainsString('Stencil popover', $footer);
        // the row that is rendered for real still portals: the positive control that the footer is read right
        $this->assertStringContainsString('Row popover', $footer);
        $this->assertStringNotContainsString('Row popover', $html);
    }

    private function readFooterData(): string
    {
        // Read the footer off the exact PageRenderer instance PortalRegistry was constructed with,
        // rather than one freshly resolved via the testing framework's own container - the two
        // containers aren't guaranteed to hand back the same shared PageRenderer instance.
        $pageRenderer = (fn(): PageRenderer => $this->pageRenderer)->call(PortalRegistry::getInstance());

        /** @var string[] $footerData */
        $footerData = (fn(): array => $this->footerData)->call($pageRenderer);

        return implode("\n", $footerData);
    }
}
