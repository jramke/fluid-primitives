<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Controller;

use Jramke\FluidPrimitives\Attributes\Ajax;
use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Domain\Model\AjaxComponentActionDemand;
use Jramke\FluidPrimitives\Factory\ComponentContextFactory;
use Jramke\FluidPrimitives\Service\ComponentCollectionService;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;

class AjaxDispatcherController extends ActionController
{
    public function __construct(
        private readonly RenderingContextFactory $renderingContextFactory,
        private readonly ComponentCollectionService $componentCollectionService,
    ) {}

    public function dispatchAction(): ResponseInterface
    {
        try {
            $demand = AjaxComponentActionDemand::fromRequest($this->request);

            $componentCollection = $this->componentCollectionService->getCollectionByViewHelperName(
                $demand->getFullComponentName(),
            );
            if (!$componentCollection instanceof ComponentCollectionInterface) {
                throw new \RuntimeException('Invalid component collection.', 1784368368);
            }

            $contextClassName = ComponentUtility::getContextClassNameFromViewHelperName(
                $demand->getViewHelperName(),
                $componentCollection->getContextNamespaces(),
            );

            $renderingContext = $this->renderingContextFactory->create(request: $this->request);

            // We are basically a root component here
            $renderingContext->getVariableProvider()->add('rootId', $demand->getProps()['id'] ?? null);

            $contextFactory = GeneralUtility::makeInstance(ComponentContextFactory::class);
            $context = $contextFactory->create(
                $contextClassName,
                $renderingContext,
                clone $renderingContext,
                $componentCollection,
                $demand->getPreparedPropsForContext(),
            );

            ContextService::addToRenderingContext(
                $renderingContext,
                ComponentUtility::getComponentBaseNameFromViewHelperName($demand->getViewHelperName()),
                $context,
            );

            $contextMethod = $demand->getMethod();
            $contextReflection = new ReflectionClass($context);

            if (!$contextReflection->hasMethod($contextMethod)) {
                throw new \RuntimeException(
                    sprintf('Method "%s" does not exist in context class "%s".', $contextMethod, $contextClassName),
                    1784664081,
                );
            }

            $method = $contextReflection->getMethod($contextMethod);

            if (!$method->isPublic()) {
                throw new \RuntimeException(
                    sprintf('Method "%s" in context class "%s" is not public.', $contextMethod, $contextClassName),
                    1784664082,
                );
            }

            $methodHasAjaxAttribute = $method->getAttributes(Ajax::class) !== [];
            if (!$methodHasAjaxAttribute) {
                throw new \RuntimeException(
                    sprintf(
                        'Method "%s" in context class "%s" is not annotated with the "%s" attribute.',
                        $contextMethod,
                        $contextClassName,
                        Ajax::class,
                    ),
                    1784664083,
                );
            }

            if ($method->getNumberOfRequiredParameters() !== count($demand->getArguments())) {
                throw new \RuntimeException(
                    sprintf(
                        'Method "%s" in context class "%s" expects %d arguments, but %d were provided.',
                        $contextMethod,
                        $contextClassName,
                        $method->getNumberOfRequiredParameters(),
                        count($demand->getArguments()),
                    ),
                    1784664084,
                );
            }

            $result = $context->{$contextMethod}(...$demand->getArguments());

            return $this->jsonResponse(json_encode([
                'success' => true,
                'data' => $result,
            ]))->withStatus(200);
        } catch (\Throwable $th) {
            return $this->jsonResponse(json_encode([
                'success' => false,
                'error' => $th->getMessage(),
            ]))->withStatus(500);
        }
    }
}
