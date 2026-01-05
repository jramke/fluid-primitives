<?php

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Utility\ComponentUtility;

trait ComponentAssetAutoLoaderTrait
{
    /**
     * Components already checked in this request
     *
     * @var array<string, bool>
     */
    protected static array $checkedComponents = [];

    /**
     * Components with assets
     *
     * @var array<string, string> viewHelperName => entryFileRelativeToExt
     */
    protected static array $componentsWithAssets = [];

    /**
     * Must be provided by the consuming class.
     *
     * @return string[]
     */
    public function getComponentEntryExtensions(string $viewHelperName): array
    {
        return [];
    }

    /**
     * Must be provided by the consuming class.
     */
    public function loadComponentAsset(string $fileName, string $viewHelperName) {}

    /**
     * Resolve a component entry file once per request and delegate usage
     * to a caller-provided callback.
     *
     * @param string   $viewHelperName
     * @param callable $onEntryFound function(string $entryFile, string $viewHelperName): void
     */
    public function autoLoadComponentAssets(
        string $viewHelperName,
        callable $onEntryFound,
        array|null $entryExtensions = null
    ): void {
        if (!ComponentUtility::isRootComponent($viewHelperName)) {
            return;
        }

        if (isset(self::$checkedComponents[$viewHelperName])) {
            return;
        }

        self::$checkedComponents[$viewHelperName] = true;

        $templateName = $this->resolveTemplateName($viewHelperName);
        $fileName = $this->getTemplatePaths()
            ->resolveTemplateFileForControllerAndActionAndFormat(
                'Default',
                $templateName
            );

        $componentFolder = dirname($fileName) . '/';
        $ucFirstComponentBaseName = ucfirst(explode('.', $viewHelperName)[0] ?? '');

        $possibleEntryFiles = array_map(
            fn($extension) => $componentFolder . $ucFirstComponentBaseName . $extension,
            $entryExtensions ?? $this->getComponentEntryExtensions($viewHelperName)
        );

        foreach ($possibleEntryFiles as $entryFile) {
            if (!is_file($entryFile)) {
                continue;
            }

            self::$componentsWithAssets[$viewHelperName] = $entryFile;

            $onEntryFound($entryFile, $viewHelperName);
        }
    }
}
