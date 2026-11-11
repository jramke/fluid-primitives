<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * Builds the URLs of a pagination with typolink, so they carry a cHash and go through the route
 * enhancers of the site.
 */
final readonly class PaginationLinkBuilder
{
    public function __construct(
        private LinkFactory $linkFactory,
        private ExtensionService $extensionService,
    ) {}

    /**
     * The URL of the current page with `$argument` set to `$page`. Inside an Extbase plugin, the
     * argument sits in the namespace of the plugin, where its controller reads it. Page 1 has no
     * argument, so it has the URL of the list itself.
     */
    public function build(ServerRequestInterface $request, string $argument, int $page, ?string $section): ?string
    {
        $namespace = $request instanceof ExtbaseRequestInterface
            ? $this->extensionService->getPluginNamespace(
                $request->getControllerExtensionName(),
                $request->getPluginName(),
            )
            : null;

        $configuration = [
            'parameter' => 'current',
            'addQueryString' => 'untrusted',
            'addQueryString.' => [
                'exclude' => $namespace === null ? $argument : sprintf('%s[%s]', $namespace, $argument),
            ],
        ];
        if ($section !== null && $section !== '') {
            $configuration['section'] = $section;
        }
        if ($page > 1) {
            $configuration['queryParameters'] = $namespace === null
                ? [$argument => $page]
                : [$namespace => [$argument => $page]];
        }

        $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObject->setRequest($request);
        try {
            return $this->linkFactory->create('', $configuration, $contentObject)->getUrl();
        } catch (UnableToLinkException) {
            return null;
        }
    }
}
