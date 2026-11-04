<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ToasterRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersALiveRegionAroundATemplateOfOneToast(): void
    {
        $html = $this->renderTemplate('
            <primitives:toaster rootId="toaster" placement="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\ToastPlacement::TopEnd\')}" translations="{closeTriggerLabel: \'Close\'}">
                <primitives:toaster.toastTemplate>
                    <primitives:toast.root>
                        <primitives:toast.title />
                        <primitives:toast.closeTrigger>x</primitives:toast.closeTrigger>
                    </primitives:toast.root>
                </primitives:toaster.toastTemplate>
            </primitives:toaster>
        ');

        $region = $this->elementWith($html, 'data-toaster-root');
        $this->assertSame('toaster', $region->get('data-toaster-root'));
        $this->assertSame('region', $region->get('role'));
        $this->assertSame('polite', $region->get('aria-live'));

        // the toasts only exist on the client, cloned from the template
        $this->assertSame(
            'toaster',
            $this->elementWith($html, 'data-toaster-toast-template')->get('data-toaster-toast-template'),
        );
        $toastId = $this->elementWith($html, 'data-toast-root')->get('data-toast-root');
        $this->assertSame(1, preg_match('/<template\s[^>]*>\s*<div\s[^>]*data-toast-root="/', $html));
        $this->assertSame($toastId, $this->elementWith($html, 'data-toast-title')->get('data-toast-title'));

        $props = HydrationRegistry::getInstance()->get('primitives', 'toaster', 'toaster')['props'] ?? [];
        $this->assertSame('top-end', $props['placement']);
        $this->assertSame(['groupLabel' => 'Notifications', 'closeTriggerLabel' => 'Close'], $props['translations']);
    }
}
