<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Traits\JsonValidationErrorsTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
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
        // an error of the argument itself is keyed by its bare name, which the Form shows as a form-level message
        $result->forProperty('eventRegistration')->addError(new Error('Registration closed', 4));
        // a property of a collection item is keyed by its index
        $result->forProperty('guests.0.email')->addError(new Error('Invalid email', 5));

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

            public function callErrorAction(): ResponseInterface
            {
                $this->errorAction();
                return $this->htmlResponse();
            }
        };

        try {
            $controller->callErrorAction();
        } catch (PropagateResponseException $exception) {
            $response = $exception->getResponse();
        }

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        // the order of the keys is Extbase's, not part of the contract
        $this->assertEquals(
            [
                'eventRegistration.email' => ['Invalid email', 'Too long'],
                'eventRegistration.name' => ['Required'],
                'eventRegistration' => ['Registration closed'],
                'guests.0.email' => ['Invalid email'],
            ],
            json_decode((string)$response->getBody(), true),
        );
    }
}
