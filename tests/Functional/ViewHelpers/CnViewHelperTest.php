<?php

declare(strict_types=1);

describe('CnViewHelper', function () {
    describe('basic class rendering', function () {
        it('renders a simple class string', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'my-class\')}');
            expect($result)->toBe('my-class');
        });

        it('renders multiple space-separated classes', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'class-one class-two class-three\')}');
            expect($result)->toBe('class-one class-two class-three');
        });

        it('trims whitespace from class names', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'  my-class  \')}');
            expect($result)->toBe('my-class');
        });

        it('normalizes multiple spaces between classes', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'class-one    class-two\')}');
            expect($result)->toBe('class-one class-two');
        });

        it('handles multiline class strings', function () {
            $result = $this->renderTemplate("<ui:cn value=\"class-one\n    class-two\n    class-three\" />");
            expect($result)->toBe('class-one class-two class-three');
        });

        it('returns empty string for empty value', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'\')}');
            expect($result)->toBe('');
        });

        it('returns empty string for whitespace-only value', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'   \')}');
            expect($result)->toBe('');
        });

        it('deduplicates repeated classes', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'my-class my-class my-class\')}');
            expect($result)->toBe('my-class');
        });
    });

    describe('conditional classes with when argument', function () {
        it('includes class when condition is true', function () {
            $this->assign('conditions', ['conditional-class' => true]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('conditional-class');
        });

        it('excludes class when condition is false', function () {
            $this->assign('conditions', ['conditional-class' => false]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('handles mixed true/false conditions', function () {
            $this->assign('conditions', [
                'included' => true,
                'excluded' => false,
                'also-included' => true,
            ]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('included also-included');
        });

        it('combines value and when arguments', function () {
            $this->assign('conditions', ['conditional' => true]);
            $result = $this->renderTemplate('{ui:cn(value: \'base-class\', when: conditions)}');
            expect($result)->toBe('base-class conditional');
        });

        it('supports multiple classes per condition', function () {
            $this->assign('conditions', ['class-one class-two' => true]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('class-one class-two');
        });

        it('handles indexed array values as unconditional classes', function () {
            $this->assign('conditions', ['always-included', 'also-always']);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('always-included also-always');
        });

        it('handles mixed indexed and associative arrays', function () {
            $this->assign('conditions', [
                'unconditional',
                'conditional' => true,
                'excluded' => false,
            ]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('unconditional conditional');
        });
    });

    describe('truthiness evaluation', function () {
        it('treats string "false" as falsy', function () {
            $this->assign('conditions', ['my-class' => 'false']);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats string "0" as falsy', function () {
            $this->assign('conditions', ['my-class' => '0']);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats empty string as falsy', function () {
            $this->assign('conditions', ['my-class' => '']);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats non-empty string as truthy', function () {
            $this->assign('conditions', ['my-class' => 'yes']);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('my-class');
        });

        it('treats numeric 0 as falsy', function () {
            $this->assign('conditions', ['my-class' => 0]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats numeric 1 as truthy', function () {
            $this->assign('conditions', ['my-class' => 1]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('my-class');
        });

        it('treats null as falsy', function () {
            $this->assign('conditions', ['my-class' => null]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats empty array as falsy', function () {
            $this->assign('conditions', ['my-class' => []]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('');
        });

        it('treats non-empty array as truthy', function () {
            $this->assign('conditions', ['my-class' => ['item']]);
            $result = $this->renderTemplate('{ui:cn(when: conditions)}');
            expect($result)->toBe('my-class');
        });
    });

    describe('as argument for variable assignment', function () {
        it('assigns result to variable instead of outputting', function () {
            $result = $this->renderTemplate('<ui:cn value="my-class" as="className" />{className}');
            expect($result)->toBe('my-class');
        });

        it('returns empty string when using as argument', function () {
            $result = $this->renderTemplate('{ui:cn(value: \'my-class\', as: \'className\')}');
            expect($result)->toBe('');
        });
    });

    describe('tag-style usage', function () {
        it('renders class from tag content', function () {
            $result = $this->renderTemplate('<ui:cn>my-class</ui:cn>');
            expect($result)->toBe('my-class');
        });

        it('handles multiline content in tag style', function () {
            $result = $this->renderTemplate('<ui:cn>
                class-one
                class-two
                class-three
            </ui:cn>');
            expect($result)->toBe('class-one class-two class-three');
        });
    });
});
