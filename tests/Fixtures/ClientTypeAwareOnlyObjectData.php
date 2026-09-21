<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

/**
 * The `getTsShapeClass()` target for {@see ClientTypeAwareOnlyObject} - never reflected by real
 * codegen in these tests (they only cover {@see \Jramke\FluidPrimitives\Service\Component\ClientPropValueResolver}'s
 * runtime `instanceof` dispatch), so it carries no `#[TypeScript]` attribute; it exists purely so
 * `getTsShapeClass()`'s `class-string` contract points at a real class, same as production code.
 */
final class ClientTypeAwareOnlyObjectData {}
