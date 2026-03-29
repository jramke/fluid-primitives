<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;

describe('TagAttributes', function () {
    describe('rendering', function () {
        it('renders key-value and boolean attributes', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
            expect((string)$attrs)->toBe('class="test" id="myId" disabled');
        });

        it('renders aria- attributes with boolean values as strings', function () {
            $attrs = new TagAttributes(['aria-expanded' => true, 'aria-hidden' => false]);
            expect((string)$attrs)->toBe('aria-expanded="1" aria-hidden');
        });

        it('escapes HTML in values and keys', function () {
            $attrs = new TagAttributes([
                'data-value' => '<script>alert("xss")</script>',
                'data-<test>' => 'value',
            ]);
            expect((string)$attrs)->toContain('&lt;script&gt;');
            expect((string)$attrs)->toContain('data-&lt;test&gt;');
        });

        it('encodes arrays and objects as JSON', function () {
            $obj = new \stdClass();
            $obj->name = 'test';
            $attrs = new TagAttributes(['data-items' => ['a', 'b'], 'data-obj' => $obj]);
            expect((string)$attrs)->toContain('data-items="[&quot;a&quot;,&quot;b&quot;]"');
            expect((string)$attrs)->toContain('data-obj="{&quot;name&quot;:&quot;test&quot;}"');
        });
    });

    describe('renderWithOnly', function () {
        it('filters to specified keys only', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
            expect($attrs->renderWithOnly(['class']))->toBe('class="test"');
            expect($attrs->renderWithOnly(['class'], true))->toBe(['class' => 'test']);
        });
    });

    describe('renderWithSkip', function () {
        it('excludes specified keys', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
            expect($attrs->renderWithSkip(['id', 'disabled']))->toBe('class="test"');
            expect($attrs->renderWithSkip(['id'], true))->toBe(['class' => 'test', 'disabled' => '']);
        });
    });

    describe('stringToArray', function () {
        it('parses mixed key-value and boolean attributes', function () {
            $result = TagAttributes::stringToArray('class="test" disabled');
            expect($result)->toBe(['class' => 'test', 'disabled' => true]);
        });

        it('handles values with equals signs', function () {
            $result = TagAttributes::stringToArray('data-equation="1+1=2"');
            expect($result)->toBe(['data-equation' => '1+1=2']);
        });
    });
});
