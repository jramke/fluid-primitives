<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Renders a single component in isolation - outside of any page-level Fluid
 * view - given just a "namespace:name" ViewHelper name and an arguments
 * array, e.g. `render('ui:formRecurringFieldsExample.row', ['index' => 3], $request)`.
 *
 * This goes through the exact same rendering pipeline every component goes
 * through during a normal page render (Context class instantiation, its
 * lifecycle hooks, #[ExposeToClient] hydration data collection - all of it),
 * just triggered directly from a controller action instead of as a side
 * effect of compiling a page template. Component templates and Context
 * classes do not need to know or care that they are being rendered this way.
 *
 * This is the building block for "fetch a fragment from the server" client
 * interactions: lazily rendering a new recurring-field row, a combobox's
 * search results, a file-upload preview, and similar cases where a piece of
 * UI needs to be rendered with full Fluid templating power in response to
 * client-side state rather than up front on the page.
 *
 * Deliberately not exposed as a single "render any component by name" HTTP
 * endpoint - pair this with an explicit controller action per fragment you
 * want to expose (see {@see \Jramke\FluidPrimitives\Traits\ComponentFragmentTrait}),
 * so only components you have explicitly wired up this way become
 * client-callable.
 */
#[Autoconfigure(public: true)]
class ComponentFragmentRenderer
{
    public function __construct(
        private readonly ComponentCollectionService $componentCollectionService,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     * @return array{html: string, hydrationData: array<string, mixed>}
     */
    public function render(string $viewHelperName, array $arguments, RenderingContextInterface $renderingContext): array
    {
        $name = $this->getComponentName($viewHelperName);
        $collection = $this->componentCollectionService->getCollectionByViewHelperName($viewHelperName);
        $componentRenderer = $collection->getComponentRenderer();

        // ComponentRenderer always writes hydration data through the
        // HydrationRegistry::getInstance() static accessor (a hard singleton,
        // not a container-resolved one), so we must read through the exact
        // same accessor rather than a constructor-injected instance, which
        // could be a different object across container rebuilds.
        $hydrationRegistry = HydrationRegistry::getInstance();

        // A fragment render is its own isolated request, but the registry is a
        // shared singleton - clear it first so hydrationData only ever reflects
        // this one fragment, even if something earlier in the same request
        // already touched it.
        $hydrationRegistry->clear();

        $html = $componentRenderer->renderComponent($name, $arguments, [], $renderingContext);

        return [
            'html' => $html,
            'hydrationData' => $hydrationRegistry->getAll(),
        ];
    }

    private function getComponentName(string $viewHelperName): string
    {
        if (!str_contains($viewHelperName, ':')) {
            throw new \RuntimeException(
                'Invalid component name "' . $viewHelperName . '", expected "namespace:name"',
                1767900000,
            );
        }

        [, $name] = explode(':', $viewHelperName, 2);

        return $name;
    }
}
