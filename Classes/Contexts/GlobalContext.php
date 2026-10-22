<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The library's global context: `locale` and `debug`. A project extends it with getters of its own and
 * registers it with `#[AsAlias(GlobalContextInterface::class, public: true)]`. `getLocale()` is `final`, the library relies on
 * what it returns; `getDebug()` can be overridden, e.g. to turn the client's dev warnings on for a staging
 * site. An override repeats `#[ExposeToClient]`, an attribute is not inherited.
 *
 * Takes no constructor arguments, so a subclass can use its own constructor without passing any on.
 */
#[Autoconfigure(public: true)]
class GlobalContext extends AbstractGlobalContext
{
    /** The language of the site as a BCP 47 tag (`de-DE`), `null` without a request. */
    #[ExposeToClient(excludeIfNull: true)]
    final public function getLocale(): ?string
    {
        $request = $this->getRequest();

        return $request instanceof ServerRequestInterface
            ? GeneralUtility::makeInstance(TranslatorService::class)->getLocale($request)
            : null;
    }

    /** Whether TYPO3's Application Context is development: the client prints its dev-only warnings. */
    #[ExposeToClient]
    public function getDebug(): bool
    {
        try {
            return Environment::getContext()->isDevelopment();
        } catch (\Throwable) {
            // Environment is not initialized outside TYPO3 (unit tests), which counts as production
            return false;
        }
    }
}
