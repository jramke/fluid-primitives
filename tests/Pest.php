<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific
 | test case class. By default, that class is "PHPUnit\Framework\TestCase".
 | You can change this by using the "pest()->extend()" function to bind
 | a different class or trait to your test suite.
 |
 */

pest()->extend(Jramke\FluidPrimitives\Tests\TestCase::class)->in('Unit');
pest()->extend(Jramke\FluidPrimitives\Tests\ViewHelperTestCase::class)->in('Functional/ViewHelpers');
pest()->extend(Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase::class)->in('Functional/Components');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain
 | conditions. Pest provides a set of expectations that allow you to write
 | more expressive tests.
 |
 */

expect()->extend('toBeValidClassName', function () {
    return $this->toBeString()->toMatch('/^[A-Z][a-zA-Z0-9_\\\\]*$/');
});

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code
 | specific to your project that you don't want to repeat in every file.
 | Here you can also define your own helper functions.
 |
 */
