<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Call a method on an object with optional arguments.
 * 
 * Used internally and exposed for convenience.
 * 
 * ## Examples
 *
 * ``` 
 * {object -> ui:call(method: 'doSomething')}
 * {object -> ui:call(method: 'doSomethingWithArguments', arguments: {0: 'foo', 1: 'bar'})}
 * ```
 */

class CallViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('object', 'object', 'The object to call a method on', false);
        $this->registerArgument('method', 'string', 'The method name to call', true);
        $this->registerArgument('arguments', 'array', 'Arguments to pass to the method', false, []);
    }

    public function render(): mixed
    {
        $object = $this->renderChildren();
        if (!$object) {
            throw new \RuntimeException('No object provided to call method on.');
        }

        $method = $this->arguments['method'];
        $args = $this->arguments['arguments'] ?? [];

        if (!is_object($object)) {
            throw new \RuntimeException('The provided value is not an object.');
        }
        if (!method_exists($object, $method)) {
            throw new \RuntimeException(sprintf(
                'Method "%s" does not exist on object of type %s',
                $method,
                get_class($object)
            ));
        }

        return call_user_func_array([$object, $method], $args);
    }

    public function getContentArgumentName(): string
    {
        return 'object';
    }
}
