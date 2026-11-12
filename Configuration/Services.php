<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// Every component root gets its own context, created by the container so its constructor can take services. This
// also covers a project's own context class: registered for the whole container, unlike `_instanceof` in
// Services.yaml, which only reaches the services of its own file.
return static function (ContainerConfigurator $configurator, ContainerBuilder $container): void {
    $container->registerForAutoconfiguration(ComponentContextInterface::class)->setShared(false)->setPublic(true);
};
