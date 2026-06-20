<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Tests\ViewHelperTestCase;

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

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(ViewHelperTestCase::class)->in('Functional/ViewHelpers');
pest()->extend(FunctionalTestCase::class)->in('Functional/Components');

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
