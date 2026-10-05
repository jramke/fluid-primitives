<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * Makes a request safe to retry: the client sends a per-intent `X-Idempotency-Key` header, the first
 * successful (2xx) response is stored, and any request repeating the key gets that response replayed
 * instead of running the action again.
 *
 * Entries are scoped to the request path, query and frontend session, so keys can't be replayed
 * across actions or visitors. A request arriving while another one with the same key is still
 * running is answered with 409. Failed responses are never stored, so a retry re-runs the action.
 * Requests without the header just run the callback.
 */
final class IdempotencyService
{
    public const HEADER = 'X-Idempotency-Key';
    public const REPLAY_HEADER = 'X-Idempotent-Replayed';
    public const CACHE_IDENTIFIER = 'fluidprimitives_idempotency';

    private const KEY_PATTERN = '/^[A-Za-z0-9_-]{8,128}$/';

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly LockFactory $lockFactory,
    ) {}

    /**
     * @param callable(): ResponseInterface $callback
     */
    public function execute(ServerRequestInterface $request, callable $callback): ResponseInterface
    {
        if (!$request->hasHeader(self::HEADER)) {
            return $callback();
        }

        $key = trim($request->getHeaderLine(self::HEADER));
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return new JsonResponse(['message' => 'Invalid idempotency key.'], 400);
        }

        $entryIdentifier = $this->entryIdentifier($request, $key);
        $cache = $this->cacheManager->getCache(self::CACHE_IDENTIFIER);

        $replayed = $this->replay($cache, $entryIdentifier);
        if ($replayed !== null) {
            return $replayed;
        }

        $locker = $this->lockFactory->createLocker(
            'idempotency-' . $entryIdentifier,
            LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK,
        );

        try {
            $acquired = $locker->acquire(
                LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK,
            );
        } catch (LockAcquireWouldBlockException) {
            $acquired = false;
        }

        if (!$acquired) {
            return new JsonResponse(['message' => 'A request with this idempotency key is still running.'], 409);
        }

        try {
            // Another request may have finished between the first lookup and acquiring the lock.
            $replayed = $this->replay($cache, $entryIdentifier);
            if ($replayed !== null) {
                return $replayed;
            }

            $response = $callback();
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $cache->set($entryIdentifier, [
                    'status' => $response->getStatusCode(),
                    'contentType' => $response->getHeaderLine('Content-Type'),
                    'body' => (string) $response->getBody(),
                ]);
            }

            return $response;
        } finally {
            $locker->release();
        }
    }

    private function replay(FrontendInterface $cache, string $entryIdentifier): ?ResponseInterface
    {
        $entry = $cache->get($entryIdentifier);
        if (!is_array($entry) || !isset($entry['status'], $entry['body']) || !is_int($entry['status'])) {
            return null;
        }

        $headers = [self::REPLAY_HEADER => '1'];
        if (isset($entry['contentType']) && is_string($entry['contentType']) && $entry['contentType'] !== '') {
            $headers['Content-Type'] = $entry['contentType'];
        }

        $response = new Response('php://temp', $entry['status'], $headers);
        $response->getBody()->write(is_string($entry['body']) ? $entry['body'] : '');

        return $response;
    }

    private function entryIdentifier(ServerRequestInterface $request, string $key): string
    {
        $uri = $request->getUri();
        $frontendUser = $request->getAttribute('frontend.user');
        $session = is_object($frontendUser) && method_exists($frontendUser, 'getSession')
            ? $frontendUser->getSession()
            : null;
        $sessionId = is_object($session) && method_exists($session, 'getIdentifier')
            ? (string) $session->getIdentifier()
            : '';

        return hash('sha256', implode("\0", [$request->getMethod(), $uri->getPath(), $uri->getQuery(), $sessionId, $key]));
    }
}
