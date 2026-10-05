<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Service\IdempotencyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Opt-in idempotency for actions that create something and may be retried by the client:
 *
 *     return $this->idempotent(function (): ResponseInterface {
 *         $this->repository->add($comment);
 *         return $this->jsonResponse(json_encode(['id' => $comment->getUid()]));
 *     });
 *
 * @property-read ServerRequestInterface $request Provided by the using class extending {@see ActionController}.
 */
trait IdempotentActionTrait
{
    /**
     * @param callable(): ResponseInterface $action
     */
    protected function idempotent(callable $action): ResponseInterface
    {
        return GeneralUtility::makeInstance(IdempotencyService::class)->execute($this->request, $action);
    }
}
