<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\Typed;
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
        // renderChildren() is inherently mixed - this ViewHelper's whole purpose is calling a method
        // on whatever the rendered children produced, checked via is_object() below.
        // @mago-expect analysis:mixed-assignment
        $object = $this->renderChildren();
        if (!$object) {
            throw new \RuntimeException('No object provided to call method on.', 2131365274);
        }

        $method = Typed::string($this->arguments['method']);
        $args = Typed::arrayOrNull($this->arguments['arguments']) ?? [];

        if (!is_object($object)) {
            throw new \RuntimeException('The provided value is not an object.', 2653378988);
        }
        if (!method_exists($object, $method)) {
            throw new \RuntimeException(
                sprintf('Method "%s" does not exist on object of type %s', $method, $object::class),
                9125095715,
            );
        }

        // This ViewHelper's entire purpose is calling an arbitrary, template-supplied method name -
        // dynamic dispatch is inherent here, not something a rewrite would resolve.
        // @mago-expect analysis:string-member-selector
        return $object->{$method}(...$args);
    }

    public function getContentArgumentName(): string
    {
        return 'object';
    }
}
