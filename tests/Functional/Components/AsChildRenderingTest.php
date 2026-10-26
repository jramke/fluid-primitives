<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Exception\InvalidSlotContentException;
use Jramke\FluidPrimitives\Tests\Fixtures\PlainComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

/**
 * `asChild` is one mechanism (`AsChildAttributeSpreader`) that every part with `{ui:asChild()}` shares, so it is
 * proven here on a dialog trigger and not again for every component.
 */
final class AsChildRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTheChildInPlaceOfTheTemplateElementCarryingTheComponentAttributes(): void
    {
        $asChild = $this->renderDialogTrigger('<a href="/some-link">Open Dialog</a>');
        $trigger = $this->elementWith($asChild, 'data-dialog-trigger');

        // one element carries the marker: the template's own <button> is gone, not wrapped around the child
        $this->assertSame('a', $trigger->tag);
        $this->assertSame('/some-link', $trigger->get('href'));
        $this->assertSame('closed', $trigger->get('data-state'));

        $template = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{false}">Open</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');
        $this->assertSame('button', $this->elementWith($template, 'data-dialog-trigger')->tag);

        // the marker only tells the spreader which tag to merge onto, it is never part of the output
        $this->assertStringNotContainsString(Constants::AS_CHILD_TARGET_MARKER, $asChild);
        $this->assertStringNotContainsString(Constants::AS_CHILD_TARGET_MARKER, $template);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function children(): iterable
    {
        yield 'a button with its own attributes' => [
            '<button type="submit" class="my-custom-class" data-custom="value">Open</button>',
            'button',
            ['type' => 'submit', 'class' => 'my-custom-class', 'data-custom' => 'value'],
        ];
        yield 'a div acting as a button' => [
            '<div role="button" tabindex="0">Clickable</div>',
            'div',
            ['role' => 'button', 'tabindex' => '0'],
        ];
        yield 'a span' => ['<span class="trigger-span">Click me</span>', 'span', ['class' => 'trigger-span']];
        yield 'boolean attributes' => [
            '<button disabled autofocus>Open</button>',
            'button',
            ['disabled' => '', 'autofocus' => ''],
        ];
        yield 'a void element' => [
            '<input type="button" value="Open" />',
            'input',
            ['type' => 'button', 'value' => 'Open'],
        ];
    }

    /**
     * @param array<string, string> $expectedAttributes
     */
    #[Test]
    #[DataProvider('children')]
    public function keepsEveryAttributeOfTheChildWhateverItsElement(
        string $child,
        string $expectedTag,
        array $expectedAttributes,
    ): void {
        $trigger = $this->elementWith($this->renderDialogTrigger($child), 'data-dialog-trigger');

        $this->assertSame($expectedTag, $trigger->tag);
        foreach ($expectedAttributes as $name => $value) {
            $this->assertSame($value, $trigger->get($name), $name);
        }
    }

    #[Test]
    public function rejectsMoreThanOneTopLevelChild(): void
    {
        $this->assertRenderThrows(
            InvalidSlotContentException::class,
            'asChild requires exactly one child element',
            function (): void {
                $this->renderDialogTrigger('<a href="/one">One</a><span>Two</span>');
            },
        );
    }

    #[Test]
    public function acceptsOneChildWithNestedAndVoidElementsAndComments(): void
    {
        $html = $this->renderDialogTrigger(
            '<button><span>icon</span> text <img src="x.svg"><br/><b>bold</b></button><!-- end -->',
        );

        $this->assertSame('button', $this->elementWith($html, 'data-dialog-trigger')->tag);
    }

    #[Test]
    public function theChildsAttributesWinWhereTheyConflictWithTheComponents(): void
    {
        $html = $this->renderDialogTrigger(
            '<button class="child-class" data-state="child-state">Open</button>',
            'class="component-class"',
        );

        $trigger = $this->elementWith($html, 'data-dialog-trigger');
        $this->assertSame('child-class', $trigger->get('class'));
        $this->assertSame('child-state', $trigger->get('data-state'));
    }

    #[Test]
    public function marksEveryAsChildPartOfOneRootEvenInsideAnotherPartsSlot(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root rootId="multi-aschild">
                <primitives:dialog.trigger asChild="{true}">
                    <a href="#" class="open-link">Open</a>
                </primitives:dialog.trigger>
                <primitives:dialog.content>
                    <p>Dialog content here</p>
                    <primitives:dialog.closeTrigger asChild="{true}">
                        <a href="#" class="close-link">Close</a>
                    </primitives:dialog.closeTrigger>
                </primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertSame('open-link', $this->elementWith($html, 'data-dialog-trigger')->get('class'));
        $this->assertSame('close-link', $this->elementWith($html, 'data-dialog-close-trigger')->get('class'));
        // trigger, content and close trigger, all of the one dialog
        $this->assertSame(3, preg_match_all('/data-dialog-[a-z-]+="multi-aschild"/', $html));
    }

    #[Test]
    public function supportsAsChildOnAComponentWithNoClientHydrationAtAll(): void
    {
        // asChild is opted into per component via {ui:asChild()}: it works on a plain, purely server-rendered
        // component with no ui:ref at all, like a userland Card
        $this->registerPlainComponents();

        $html = $this->renderTemplate('
            <plain:card asChild="{true}" class="card-class">
                <a href="/some-link" data-custom="value">Card as link</a>
            </plain:card>
        ');

        $card = $this->elementWith($html, 'data-custom');
        $this->assertSame('a', $card->tag);
        $this->assertSame('/some-link', $card->get('href'));
        $this->assertSame('card-class', $card->get('class'));
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*class="card-class"/', $html);
    }

    #[Test]
    public function preservesSingleLevelOfEscapingForClassContainingAmpersand(): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}" class="[&_svg]:size-4">
                    <a href="/some-link">Open</a>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');

        $this->assertStringContainsString('class="[&amp;_svg]:size-4"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function valuesThatReadAsReplacementReferences(): iterable
    {
        yield 'the whole match, which carries the quotes of the tag' => ['$0 onmouseover=alert(1) x='];
        yield 'a backslash reference' => ['\\0 onmouseover=alert(1) x='];
        yield 'a braced reference' => ['${0} onmouseover=alert(1) x='];
        yield 'text that merely contains them' => ['Cost $10 \\\\ $1 ${2}'];
    }

    #[Test]
    #[DataProvider('valuesThatReadAsReplacementReferences')]
    public function keepsAttributeValuesVerbatimThatReadAsReplacementReferences(string $value): void
    {
        $html = $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}" data-price="{value}">
                    <a href="{value}" title="{value}">Open</a>
                </primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ', ['value' => $value]);

        $trigger = $this->elementWith($html, 'data-dialog-trigger');
        $this->assertSame($value, $trigger->get('href'));
        $this->assertSame($value, $trigger->get('title'));
        $this->assertSame($value, $trigger->get('data-price'));
        $this->assertFalse($trigger->has('onmouseover'));
        $this->assertNoRepeatedAttributes($html);
    }

    #[Test]
    public function rejectsAsChildOnAComponentThatDidNotOptIn(): void
    {
        // asChild is opt-in per component via {ui:asChild()}: a component whose template never calls it
        // (NoAsChildOptIn: a plain <div>, no {ui:attributes()} either) doesn't declare asChild as an
        // argument at all, so passing it is an unknown argument like any other undeclared prop
        $this->registerPlainComponents();

        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('asChild');

        $this->renderTemplate('
            <plain:noAsChildOptIn asChild="{true}">
                <a href="/some-link">Link</a>
            </plain:noAsChildOptIn>
        ');
    }

    #[Test]
    public function targetsTheTagCarryingTheMarkerNotJustTheFirstBareTag(): void
    {
        // Toolbar nests two bare tags: a decorative outer <div data-outer="yes"> and the real target, the
        // inner <div {ui:asChild()} data-inner="yes">. Merging onto "whichever tag comes first" would grab
        // the outer one.
        $this->registerPlainComponents();

        $html = $this->renderTemplate('
            <plain:toolbar asChild="{true}">
                <a href="/some-link">Link</a>
            </plain:toolbar>
        ');

        $target = $this->elementWith($html, 'data-inner');
        $this->assertSame('a', $target->tag);
        $this->assertSame('/some-link', $target->get('href'));
        $this->assertSame([], $this->elementsWith($html, 'data-outer'));
        $this->assertStringNotContainsString('outer-wrapper', $html);
    }

    private function renderDialogTrigger(string $child, string $triggerAttributes = ''): string
    {
        return $this->renderTemplate('
            <primitives:dialog.root>
                <primitives:dialog.trigger asChild="{true}" ' .
        $triggerAttributes .
        '>' .
        $child .
        '</primitives:dialog.trigger>
                <primitives:dialog.content>Content</primitives:dialog.content>
            </primitives:dialog.root>
        ');
    }

    private function registerPlainComponents(): void
    {
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('plain', new PlainComponentCollection());
    }
}
