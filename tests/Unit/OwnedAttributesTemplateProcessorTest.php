<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Service\Component\OwnedAttributesTemplateProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OwnedAttributesTemplateProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function templates(): iterable
    {
        yield 'a literal attribute, before and after the call' => [
            '<div role="tabpanel" {ui:attributes()} data-inner="yes" hidden></div>',
            '<div role="tabpanel" {ui:attributes(skip: \'role,data-inner,hidden\')} data-inner="yes" hidden></div>',
        ];
        yield 'a conditional attribute, with a value or bare' => [
            '<div {f:if(condition: a, then: \'hidden="{hidden}"\')} {ui:attributes()} {f:if(condition: b, then: \'disabled\', else: \'readonly\')}></div>',
            '<div {f:if(condition: a, then: \'hidden="{hidden}"\')} {ui:attributes(skip: \'hidden,disabled,readonly\')} {f:if(condition: b, then: \'disabled\', else: \'readonly\')}></div>',
        ];
        yield 'only the tag that carries the call' => [
            '<span aria-hidden="true"></span><input type="text" {ui:attributes()} />',
            '<span aria-hidden="true"></span><input type="text" {ui:attributes(skip: \'type\')} />',
        ];
        yield 'a ui:ref and ui:asChild are not attributes of the template' => [
            '<div {ui:ref(name: \'content\', data: refData)} {ui:attributes()} {ui:asChild()}></div>',
            '<div {ui:ref(name: \'content\', data: refData)} {ui:attributes()} {ui:asChild()}></div>',
        ];
        yield 'a quoted value that holds an inline call or a `>`' => [
            '<div title="{a > b}" data-x="{f:if(condition: x, then: \'a\')}" {ui:attributes()}></div>',
            '<div title="{a > b}" data-x="{f:if(condition: x, then: \'a\')}" {ui:attributes(skip: \'title,data-x\')}></div>',
        ];
        yield 'other arguments stay, an explicit skip or only wins' => [
            '<div id="a" {ui:attributes(asArray: true)}></div><div id="a" {ui:attributes(skip: \'x\')}></div><div id="a" {ui:attributes(only: \'y\')}></div>',
            '<div id="a" {ui:attributes(asArray: true, skip: \'id\')}></div><div id="a" {ui:attributes(skip: \'x\')}></div><div id="a" {ui:attributes(only: \'y\')}></div>',
        ];
        yield 'a component tag forwards the attributes, its own are props' => [
            '<ui:input name="a" attributes="{ui:attributes()}" />',
            '<ui:input name="a" attributes="{ui:attributes()}" />',
        ];
        yield 'a call inside an attribute value of an element is left alone' => [
            '<div id="a" data-x="{ui:attributes()}"></div>',
            '<div id="a" data-x="{ui:attributes()}"></div>',
        ];
        yield 'a tag that sets nothing' => ['<div {ui:attributes()}></div>', '<div {ui:attributes()}></div>'];
    }

    #[Test]
    #[DataProvider('templates')]
    public function skipsWhatTheTagSetsItself(string $source, string $expected): void
    {
        $this->assertSame($expected, (new OwnedAttributesTemplateProcessor())->preProcessSource($source));
    }
}
