<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Domain\Model\TagAttributes;

describe('TagAttributes', function () {
    describe('construction and basic rendering', function () {
        it('creates empty attributes', function () {
            $attrs = new TagAttributes();
            expect((string) $attrs)->toBe('');
            expect($attrs->count())->toBe(0);
        });

        it('renders simple key-value attributes', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect((string) $attrs)->toBe('class="test" id="myId"');
            expect($attrs->count())->toBe(2);
        });

        it('renders boolean attributes (true)', function () {
            $attrs = new TagAttributes(['disabled' => true]);
            expect((string) $attrs)->toBe('disabled');
        });

        it('skips boolean attributes when false', function () {
            $attrs = new TagAttributes(['disabled' => false]);
            expect((string) $attrs)->toBe('');
        });

        it('renders aria- attributes with boolean values as strings', function () {
            $attrs = new TagAttributes(['aria-expanded' => true, 'aria-hidden' => false]);
            // aria-hidden with false becomes empty string which renders as boolean attribute
            expect((string) $attrs)->toBe('aria-expanded="1" aria-hidden');
        });

        it('escapes HTML in values', function () {
            $attrs = new TagAttributes(['data-value' => '<script>alert("xss")</script>']);
            expect((string) $attrs)->toContain('&lt;script&gt;');
        });

        it('escapes HTML in keys', function () {
            $attrs = new TagAttributes(['data-<test>' => 'value']);
            expect((string) $attrs)->toContain('data-&lt;test&gt;');
        });

        it('skips null values', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => null]);
            expect((string) $attrs)->toBe('class="test"');
        });

        it('skips empty keys', function () {
            $attrs = new TagAttributes(['' => 'value', 'class' => 'test']);
            expect((string) $attrs)->toBe('class="test"');
        });

        it('encodes arrays as JSON', function () {
            $attrs = new TagAttributes(['data-items' => ['a', 'b']]);
            expect((string) $attrs)->toContain('data-items="[&quot;a&quot;,&quot;b&quot;]"');
        });

        it('encodes objects as JSON', function () {
            $obj = new \stdClass();
            $obj->name = 'test';
            $attrs = new TagAttributes(['data-obj' => $obj]);
            expect((string) $attrs)->toContain('data-obj="{&quot;name&quot;:&quot;test&quot;}"');
        });
    });

    describe('renderAsArray', function () {
        it('returns empty array for empty attributes', function () {
            $attrs = new TagAttributes();
            expect($attrs->renderAsArray())->toBe([]);
        });

        it('returns escaped key-value pairs', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect($attrs->renderAsArray())->toBe(['class' => 'test', 'id' => 'myId']);
        });

        it('escapes HTML in values', function () {
            $attrs = new TagAttributes(['data-value' => '<b>bold</b>']);
            $result = $attrs->renderAsArray();
            expect($result['data-value'])->toBe('&lt;b&gt;bold&lt;/b&gt;');
        });

        it('accepts custom attributes array', function () {
            $attrs = new TagAttributes(['class' => 'original']);
            $result = $attrs->renderAsArray(['id' => 'custom']);
            expect($result)->toBe(['id' => 'custom']);
        });
    });

    describe('renderWithOnly', function () {
        it('filters to specified keys only', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
            expect($attrs->renderWithOnly(['class']))->toBe('class="test"');
        });

        it('returns array when asArray is true', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect($attrs->renderWithOnly(['class'], true))->toBe(['class' => 'test']);
        });

        it('returns empty string for non-matching keys', function () {
            $attrs = new TagAttributes(['class' => 'test']);
            expect($attrs->renderWithOnly(['id']))->toBe('');
        });

        it('returns empty array for non-matching keys when asArray is true', function () {
            $attrs = new TagAttributes(['class' => 'test']);
            expect($attrs->renderWithOnly(['id'], true))->toBe([]);
        });

        it('returns all attributes when keys array is empty', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect($attrs->renderWithOnly([]))->toBe('class="test" id="myId"');
        });
    });

    describe('renderWithSkip', function () {
        it('excludes specified keys', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId', 'disabled' => true]);
            expect($attrs->renderWithSkip(['id', 'disabled']))->toBe('class="test"');
        });

        it('returns array when asArray is true', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect($attrs->renderWithSkip(['id'], true))->toBe(['class' => 'test']);
        });

        it('returns all attributes when keys array is empty', function () {
            $attrs = new TagAttributes(['class' => 'test', 'id' => 'myId']);
            expect($attrs->renderWithSkip([]))->toBe('class="test" id="myId"');
        });

        it('returns empty string when all keys are skipped', function () {
            $attrs = new TagAttributes(['class' => 'test']);
            expect($attrs->renderWithSkip(['class']))->toBe('');
        });

        it('returns empty array when all keys are skipped with asArray', function () {
            $attrs = new TagAttributes(['class' => 'test']);
            expect($attrs->renderWithSkip(['class'], true))->toBe([]);
        });
    });

    describe('stringToArray', function () {
        it('parses key-value attributes', function () {
            $result = TagAttributes::stringToArray('class="test" id="myId"');
            expect($result)->toBe(['class' => 'test', 'id' => 'myId']);
        });

        it('parses boolean attributes', function () {
            $result = TagAttributes::stringToArray('disabled readonly');
            expect($result)->toBe(['disabled' => true, 'readonly' => true]);
        });

        it('parses mixed attributes', function () {
            $result = TagAttributes::stringToArray('class="test" disabled');
            expect($result)->toBe(['class' => 'test', 'disabled' => true]);
        });

        it('returns empty array for empty string', function () {
            $result = TagAttributes::stringToArray('');
            expect($result)->toBe([]);
        });

        it('handles values with equals signs', function () {
            $result = TagAttributes::stringToArray('data-equation="1+1=2"');
            expect($result)->toBe(['data-equation' => '1+1=2']);
        });
    });
});
