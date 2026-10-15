<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures\GlobalContext;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\AbstractGlobalContext;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * What a project's global context looks like: a nested client name, a server only getter and one that is
 * null. A singleton so a test can hand it out instead of the container's.
 */
final class ProjectGlobalContext extends AbstractGlobalContext implements SingletonInterface
{
    public function __construct(
        private readonly string $siteName = 'Demo',
    ) {}

    #[ExposeToClient]
    public function getSiteName(): string
    {
        return $this->siteName;
    }

    #[ExposeToClient(name: 'lightbox.icons')]
    public function getLightboxIcons(): array
    {
        return ['close' => '<svg/>'];
    }

    #[ExposeToClient]
    public function isPlain(): bool
    {
        return true;
    }

    #[ExposeToClient(excludeIfNull: true)]
    public function getMissing(): ?string
    {
        return null;
    }

    public function getDesignTokens(): array
    {
        return ['colors' => ['primary' => '#f00']];
    }
}
