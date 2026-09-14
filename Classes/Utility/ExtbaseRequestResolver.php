<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Resolves the Extbase {@see RequestInterface} attached to a rendering context, or throws - for code
 * (URI building, field name prefixing) that only makes sense within a real Extbase MVC dispatch.
 */
final readonly class ExtbaseRequestResolver
{
    public function resolveOrThrow(RenderingContextInterface $renderingContext): RequestInterface
    {
        if (!$renderingContext->hasAttribute(ServerRequestInterface::class)) {
            throw new \RuntimeException('No ServerRequestInterface found in rendering context attributes', 1765100022);
        }

        $request = $renderingContext->getAttribute(ServerRequestInterface::class);
        if (!$request instanceof RequestInterface) {
            throw new \RuntimeException(
                'The ServerRequestInterface in rendering context attributes is not an Extbase RequestInterface',
                1765100023,
            );
        }

        return $request;
    }
}
