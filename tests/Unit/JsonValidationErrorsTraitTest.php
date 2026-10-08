<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Traits\JsonValidationErrorsTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;

final class JsonValidationErrorsTraitTest extends TestCase
{
    #[Test]
    public function answersFailedValidationWithPropertyKeyedJson422(): void
    {
        $result = new Result();
        $result->forProperty('eventRegistration.email')->addError(new Error('Invalid email', 1));
        $result->forProperty('eventRegistration.email')->addError(new Error('Too long', 2));
        $result->forProperty('eventRegistration.name')->addError(new Error('Required', 3));

        $arguments = $this->createStub(Arguments::class);
        $arguments->method('validate')->willReturn($result);

        $controller = new class($arguments) extends ActionController {
            use JsonValidationErrorsTrait;

            public function __construct(Arguments $arguments)
            {
                $this->arguments = $arguments;
                $this->injectResponseFactory(new ResponseFactory());
                $this->injectStreamFactory(new StreamFactory());
            }

            public function callErrorAction(): never
            {
                $this->errorAction();
            }
        };

        try {
            $controller->callErrorAction();
        } catch (PropagateResponseException $exception) {
            $response = $exception->getResponse();
        }

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['eventRegistration.email' => ['Invalid email', 'Too long'], 'eventRegistration.name' => ['Required']],
            json_decode((string)$response->getBody(), true),
        );
    }
}
