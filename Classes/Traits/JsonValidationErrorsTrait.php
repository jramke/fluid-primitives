<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Replaces the controller's `errorAction()`: failed validation is answered with a 422 JSON response keyed
 * by property path (`{"person.email": ["message"]}`), the shape the Form component's `post()` maps onto
 * its fields.
 *
 * The response is thrown, not returned: an action inside a content-element plugin is rendered into the
 * page, so a returned body would be wrapped in the page layout (TYPO3 stops rendering the same way for
 * `throwStatus()`). Validation failed before the action ran, so there is nothing for the skipped
 * end-of-request `persistAll()` to flush.
 *
 * Deliberately not `#[\Override]`: aliasing this method into a class that keeps its own `errorAction()`
 * copies the attribute onto the alias, which has no parent method, and PHP refuses to compile that.
 *
 * @require-extends ActionController
 */
trait JsonValidationErrorsTrait
{
    protected function errorAction(): never
    {
        $messages = [];
        foreach ($this->arguments->validate()->getFlattenedErrors() as $property => $errors) {
            foreach ($errors as $error) {
                $messages[$property][] = $error->getMessage();
            }
        }

        throw new PropagateResponseException(
            $this->jsonResponse(json_encode($messages, JSON_THROW_ON_ERROR))->withStatus(422),
            1791380000,
        );
    }
}
