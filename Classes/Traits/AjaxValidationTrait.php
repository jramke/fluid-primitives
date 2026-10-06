<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;

/**
 * Answers failed argument validation with a JSON 422 instead of a rendered page, in the
 * `{ "field": ["message"] }` shape that the client's `extbase.request` maps to `errors`.
 *
 * @property-read Arguments $arguments Provided by the using class extending {@see ActionController}.
 */
trait AjaxValidationTrait
{
    /**
     * Returns a 422 JSON response when the action's arguments have validation errors, otherwise
     * `null`:
     *
     *     if ($response = $this->jsonValidationErrorResponse()) {
     *         return $response;
     *     }
     */
    protected function jsonValidationErrorResponse(): ?ResponseInterface
    {
        $messages = [];
        foreach ($this->arguments->validate()->getFlattenedErrors() as $property => $errors) {
            foreach ($errors as $error) {
                $messages[$property][] = $error->getMessage();
            }
        }

        if ($messages === []) {
            return null;
        }

        return new JsonResponse($messages, 422);
    }
}
