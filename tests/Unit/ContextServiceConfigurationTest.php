<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Fixtures\Contexts\MenuContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ContextServiceConfigurationTest extends TestCase
{
    #[Test]
    public function givesAContextOfAnotherExtensionANewInstanceFromTheContainerForEveryRoot(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/Configuration')))->load('Services.php');
        // registered the way a project's own Services.yaml registers its context: private by default, no attribute
        $container->register(MenuContext::class)->setAutoconfigured(true);
        $container
            ->register('service', \ArrayObject::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        $this->assertNotSame($container->get(MenuContext::class), $container->get(MenuContext::class));
        $this->assertSame($container->get('service'), $container->get('service'));
    }
}
