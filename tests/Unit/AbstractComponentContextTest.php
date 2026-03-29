<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;

/**
 * Tests for AbstractComponentContext methods.
 */
class ConcreteTestContext extends AbstractComponentContext
{
    private array $testVars = [];

    public function setTestVariables(array $vars): void
    {
        $this->testVars = $vars;
    }

    public function get(string $key): mixed
    {
        // Direct key lookup first
        if (array_key_exists($key, $this->testVars)) {
            return $this->testVars[$key];
        }

        // Support dot notation for nested array access
        if (!str_contains($key, '.')) {
            return null;
        }

        $segments = explode('.', $key);
        $value = $this->testVars;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

describe('AbstractComponentContext', function () {
    beforeEach(function () {
        $this->context = new ConcreteTestContext();
    });

    describe('get with dot notation', function () {
        it('supports nested property access via dot notation', function () {
            $this->context->setTestVariables([
                'scrollbar' => [
                    'orientation' => 'vertical',
                    'size' => 'small',
                ],
                'nested' => [
                    'level1' => [
                        'level2' => 'deep-value',
                    ],
                ],
            ]);

            expect($this->context->get('scrollbar.orientation'))->toBe('vertical');
            expect($this->context->get('scrollbar.size'))->toBe('small');
            expect($this->context->get('nested.level1.level2'))->toBe('deep-value');
        });

        it('returns null for non-existent nested paths', function () {
            $this->context->setTestVariables([
                'scrollbar' => [
                    'orientation' => 'vertical',
                ],
            ]);

            expect($this->context->get('scrollbar.nonexistent'))->toBeNull();
            expect($this->context->get('nonexistent.path'))->toBeNull();
            expect($this->context->get('scrollbar.orientation.deeper'))->toBeNull();
        });

        it('prefers direct key lookup over dot notation parsing', function () {
            // When a key literally contains a dot, it should be found directly
            $this->context->setTestVariables([
                'my.dotted.key' => 'direct-value',
                'my' => [
                    'dotted' => [
                        'key' => 'nested-value',
                    ],
                ],
            ]);

            expect($this->context->get('my.dotted.key'))->toBe('direct-value');
        });
    });

    describe('ArrayAccess interface', function () {
        it('supports array-style access for context variables', function () {
            $this->context->setTestVariables([
                'orientation' => 'horizontal',
                'nested' => ['value' => 'test'],
            ]);

            expect($this->context['orientation'])->toBe('horizontal');
            expect($this->context['nested.value'])->toBe('test');
            expect(isset($this->context['orientation']))->toBeTrue();
            expect(isset($this->context['nonexistent']))->toBeFalse();
        });
    });
});
