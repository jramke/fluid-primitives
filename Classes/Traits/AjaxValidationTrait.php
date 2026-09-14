<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;

/**
 * @property-read Arguments $arguments Provided by the using class extending {@see ActionController}.
 */
trait AjaxValidationTrait
{
    protected function throwJsonValidationErrorResponse(): void
    {
        if (!method_exists($this, 'jsonResponse')) {
            throw new \RuntimeException(
                'Method jsonResponse does not exist in the parent class. The jsonResponse method can only be used in Classes that extend ' .
                ActionController::class,
                1768514275,
            );
        }

        $validationErrors = $this->arguments->validate()->getFlattenedErrors();
        if ($validationErrors === []) {
            return;
        }

        $messages = [];
        foreach ($validationErrors as $property => $errors) {
            foreach ($errors as $error) {
                $messages[$property][] = $error->getMessage();
            }
        }

        if ($messages === []) {
            return;
        }

        // TODO: maybe we should alternatively provide a method that returns a valid psr7 response
        // would this work?
        // jsonResponse() is only known to exist via the runtime method_exists() check above (a trait
        // can't declare a real host-class method contract the way an abstract class can while still
        // supporting hosts that don't extend ActionController) - narrowed immediately below instead.
        // @mago-expect analysis:mixed-assignment
        $rawResponse = $this->jsonResponse(json_encode($messages) ?: null);
        if (!$rawResponse instanceof ResponseInterface) {
            throw new \RuntimeException('Method jsonResponse did not return a ResponseInterface instance.', 1768514276);
        }

        $response = $rawResponse->withStatus(422);
        throw new PropagateResponseException($response, 422);
    }
}
