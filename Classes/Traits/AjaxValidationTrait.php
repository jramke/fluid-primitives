<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extensionmanager\Controller\ActionController;

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
        $response = $this->jsonResponse(json_encode($messages))->withStatus(422);
        throw new PropagateResponseException($response, 422);
    }
}
