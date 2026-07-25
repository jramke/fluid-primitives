<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

use TYPO3\CMS\Extbase\Mvc\RequestInterface;

class AjaxComponentActionDemand
{
    public function __construct(
        private string $fullComponentName,
        private string $method,
        private array $arguments = [],
        private array $props = [],
    ) {}

    public function getFullComponentName(): string
    {
        return $this->fullComponentName;
    }

    public function getViewHelperName(): string
    {
        return explode(':', $this->fullComponentName)[1];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getProps(): array
    {
        return $this->props;
    }

    public function getPreparedPropsForContext(): array
    {
        $id = $this->props['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Invalid or missing "id" in props.', 1784663814);
        }

        $propsToReturn = $this->props;
        unset($propsToReturn['id']);
        $propsToReturn['rootId'] = $id;

        return $propsToReturn;
    }

    public static function fromRequest(RequestInterface $request): self
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            throw new \RuntimeException('Invalid request body. Expected an array.', 1783367365);
        }

        $component = $request->getQueryParams()['tx_fluidprimitives_ajaxdispatcher']['component'] ?? null;
        if (!is_string($component) || $component === '') {
            throw new \RuntimeException('No component specified in query parameters.', 1783367367);
        }
        if (!str_contains($component, ':')) {
            throw new \RuntimeException(
                'Invalid component argument. It must follow the ViewHelper name format, expected "namespace:myName"',
                1784663778,
            );
        }

        $method = $request->getQueryParams()['tx_fluidprimitives_ajaxdispatcher']['method'] ?? null;
        if (!is_string($method) || $method === '') {
            throw new \RuntimeException('No method specified in query parameters.', 1783367368);
        }

        $arguments = $body['arguments'] ?? [];
        if (!is_array($arguments)) {
            throw new \RuntimeException('Invalid arguments. Expected an array.', 1783367370);
        }

        $props = $body['props'] ?? [];
        if (!is_array($props)) {
            throw new \RuntimeException('Invalid props. Expected an array.', 1783367371);
        }

        return new self(fullComponentName: $component, method: $method, arguments: $arguments, props: $props);
    }
}
