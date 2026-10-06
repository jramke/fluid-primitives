# Optimistic UI (opt-in)

`OptimisticAction` shows the result of a request before the server answers and reconciles when it does.
Nothing changes for code that doesn't construct one. It has no DOM or Extbase knowledge; pair it with
`extbase.request` as `commit`. Toasts, focus handling and announcements are yours (e.g. `@zag-js/dom-query`
and `@zag-js/live-region`).

## Server contract

| Case | Response |
|---|---|
| Success | 2xx, JSON body optional (passed to `reconcile`, e.g. the new uid) or 204 |
| Validation failure | `422` with `{ "field": ["message"] }` |
| Other failure | any 4xx/5xx |
| Repeated idempotency key | the original response, replayed |
| Same key still running | `409` |
| Network failure/abort | client-side `status: 0` |

Actions must return a JSON/empty response, not a redirect or rendered page.

```php
class CommentController extends ActionController
{
    use AjaxValidationTrait;
    use IdempotentActionTrait;

    public function createAction(Comment $comment): ResponseInterface
    {
        if ($response = $this->jsonValidationErrorResponse()) {
            return $response; // 422
        }
        // Creates must be idempotent: a retry after a timeout must not create a duplicate.
        return $this->idempotent(function () use ($comment): ResponseInterface {
            $this->repository->add($comment);
            $this->persistenceManager->persistAll();
            return $this->jsonResponse(json_encode(['id' => $comment->getUid()]));
        });
    }
}
```

Argument validation normally forwards to `errorAction` before your action runs; override `errorAction` to
return `$this->jsonValidationErrorResponse()` if you want the action body to run only for valid input.
`idempotent()` only does something when the `X-Idempotency-Key` header is present (sent by
`extbase.request` via `idempotencyKey`). Responses are cached in `fluidprimitives_idempotency`
(24 h, database backend; reconfigure via `$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']`). Only 2xx
responses are stored, scoped to path, query and frontend session. Deletes are naturally idempotent:
return success for "already gone".

## Options

- `initial`, `apply(state, intent)` (pure), `commit(intent, { signal, idempotencyKey, attempt })`.
- `reconcile(state, result, intent)` - `state` already has the intent applied.
- `isFailure(result)` - defaults to `result.ok === false`.
- `onError(ctx)` - call `ctx.rollback({ cascade? })` (default), `ctx.keep()` or `ctx.retry()` synchronously.
- `guard(intent, state)` - return `false` to skip the optimistic apply for that intent.
- `cooldownMs` (default `250`, `0` disables) - ignores `run()` calls right after an accepted one, so a held
  Enter key or double click can't hit content that moved under the pointer. Ignored runs resolve to
  `{ status: 'ignored' }`. Also skip `event.repeat` in your handler.
- `warnOnUnload` (default `true`) - while an intent is unsettled, a `beforeunload` prompt asks before
  leaving the page, since the user has seen the success but would never see the error. `false` disables;
  a function `(pending) => boolean` decides per call. The listener exists only while something is pending.

### `mode`

- `queue` (default): commits one at a time, in order. Forms, order-dependent work.
- `parallel`: all commits immediately, independently. Rows in a list.
- `latest`: aborts the in-flight commit via `signal` and replaces it. Toggles, autosave.

### Rollback, keep, retry

The visible state is `confirmed + unsettled intents`, so rolling one back never disturbs later ones.
`keep()` leaves it applied but listed in `view.failed`; `action.retry(id)` (or `ctx.retry()`) reuses the same
idempotency key, `action.discard(id)` drops it. The record's `origin` is the element focused at `run()` time.

## Form

```ts
const action = new OptimisticAction({
    initial: { sent: false },
    apply: () => ({ sent: true }),
    commit: (data, { signal, idempotencyKey }) =>
        extbase.request(url, data, { signal, idempotencyKey }),
    onError: ({ error, rollback }) => {
        rollback();
        if (error.errors) form.setErrors(error.errors); // or show your own toast
        // focus the first invalid field yourself
    },
});
action.subscribe(({ state }) => (successPanel.hidden = !state.sent));
form.addEventListener('submit', e => {
    e.preventDefault();
    action.run(Object.fromEntries(new FormData(form)));
});
```

## List with per-item delete

```ts
const action = new OptimisticAction({
    initial: items,
    mode: 'parallel',
    apply: (items, { id }) => items.filter(item => item.id !== id), // identify by id, never index
    commit: ({ id }, ctx) => extbase.request(deleteUrl, { item: id }, ctx),
    onError: ({ rollback }) => rollback(),
});
action.subscribe(({ state }) => render(state));
list.addEventListener('click', e => {
    if (e.detail === 0 && (e as KeyboardEvent).repeat) return;
    // ...
});
```

When the focused row disappears, don't move focus onto the next row's delete button (key repeat would delete
it too); focus a neutral target such as the list (`tabindex="-1"`) or heading.
