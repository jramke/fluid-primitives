<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The library's global context: `locale` and `debug`. A project extends it with getters of its own and
 * aliases {@see GlobalContextInterface} to the subclass; the two getters below are `final`, the library
 * relies on what they return.
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

        return $request === null ? null : GeneralUtility::makeInstance(TranslatorService::class)->getLocale($request);
    }

    /** Whether TYPO3's Application Context is development: the client prints its dev-only warnings. */
    #[ExposeToClient]
    final public function getDebug(): bool
    {
        try {
            return Environment::getContext()->isDevelopment();
        } catch (\Throwable) {
            // Environment is not initialized outside TYPO3 (unit tests), which counts as production
            return false;
        }
    }
}
