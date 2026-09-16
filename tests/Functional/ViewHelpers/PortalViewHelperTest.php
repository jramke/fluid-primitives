<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\PageRenderer;

final class PortalViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function rendersDefaultNamedPortalContentViaPageRendererFooterWithoutAContainer(): void
    {
        PortalRegistry::getInstance()->clearAll();

        $html = $this->renderTemplate('
            <ui:portal>
                <div data-part="portaled">Portaled content</div>
            </ui:portal>
        ');

        // No ui:portalContainer was used, yet the portal still renders nothing in place - it went
        // straight into PageRenderer's footer instead.
        $this->assertSame('', trim($html));
        $this->assertStringContainsString('data-part="portaled"', $this->readFooterData());
    }

    #[Test]
    public function onlyPushesTheDefaultNamedBucketIntoPageRendererFooter(): void
    {
        PortalRegistry::getInstance()->clearAll();

        $this->renderTemplate('
            <ui:portal name="sidebar">
                <div data-part="sidebar-portaled">Sidebar content</div>
            </ui:portal>
        ');

        $this->assertStringNotContainsString('data-part="sidebar-portaled"', $this->readFooterData());

        $sidebarPortaled = implode('', PortalRegistry::getInstance()->getAllByName('sidebar'));
        $this->assertStringContainsString('data-part="sidebar-portaled"', $sidebarPortaled);
    }

    #[Test]
    public function portalContainerStillRendersNamedPortalsInPlace(): void
    {
        PortalRegistry::getInstance()->clearAll();

        $html = $this->renderTemplate('
            <ui:portal name="sidebar">
                <div data-part="sidebar-portaled">Sidebar content</div>
            </ui:portal>
            <div id="target"><ui:portalContainer name="sidebar" /></div>
        ');

        $this->assertStringContainsString('<div id="target"><div data-part="sidebar-portaled">', $html);
        $this->assertSame([], PortalRegistry::getInstance()->getAllByName('sidebar'));
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
