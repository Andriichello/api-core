# Request traits

Three traits in `Core\Http\Requests\Traits` add behavior that several requests share: `WithObjectBody` refuses bodies
that can't be saved, `WithAccountLink` reads the signed links emailed to accounts, and `WithAssignableRoles` validates
the roles a user gives. (`WithTarget` is described in [Targets](02-targets.md).)

## WithObjectBody

For requests whose body is an object of the fields to save. `StoreRequest` and `UpdateRequest` use it.

- `sentKeys()` returns the top-level keys of the body as the client sent it, before `defaults()` and `overrides()`
  were merged into it. A request made with `createFrom()` from another of the same kind keeps the keys of the first.
- `ensureObjectBody()` throws a 422 on `body` when a JSON body is a list or a single value, which can't be mapped onto
  a model's fields: `The body must be a JSON object of the fields to save, such as {"name": "…"}.` An empty list counts
  as an empty object, since that's how PHP's and R's JSON encoders write an empty map. Form bodies aren't checked.

A request using the trait calls `ensureObjectBody()` itself, usually from `passedValidation()`, as `StoreRequest` does:

```php
protected function passedValidation(): void
{
    $this->ensureObjectBody();
}
```

Override `ensureObjectBody()` to accept another shape. `UpdateRequest` also uses `sentKeys()` to refuse updates that
send no field it knows (see [CRUD requests](01-crud-requests.md)).

## WithAccountLink

For requests that come from a link emailed to an account, which works without signing in, such as the links of email
verification (`VerifyEmailRequest`) and invitations (`AcceptInvitationRequest`). The link's parameters are the
account's `id`, a `hash` of what the link is bound to, the time it `expires` at, and a `signature` of them, signed with
the application's key together with the link's purpose, so a signature is never valid for another kind of link.

A request implements four methods:

| Method | Returns |
|---|---|
| `protected static function linkPurpose(): string` | The purpose, which is signed with the parameters |
| `protected static function linkLifetime(): int` | The minutes a link works for |
| `protected static function linkHashOf(User $account): string` | The hash of what the link is bound to: the link stops working when it changes |
| `protected function invalidLinkMessage(): string` | The message of an invalid or expired link |

And gets:

- `linkRules()`: the rules of the four parameters, to use in `rules()`;
- `static linkParameters(User $account)`: the parameters of a new link, which expires `linkLifetime()` minutes from now;
- `account()`: the account of a valid link. The link must not have expired, its signature must match (made with the
  application's key or one of `APP_PREVIOUS_KEYS`, so links survive a key rotation), and the account's hash must
  still be the link's. Otherwise it throws an `AuthorizationException` with `invalidLinkMessage()`: 403.

A link that unsubscribes an account from reminder emails, bound to its email, for 30 days:

```php
use Core\Http\Requests\BaseRequest;
use Core\Http\Requests\Traits\WithAccountLink;
use Core\Models\User;

class UnsubscribeRequest extends BaseRequest
{
    use WithAccountLink;

    public function rules(): array
    {
        return $this->linkRules();
    }

    protected static function linkPurpose(): string
    {
        return 'unsubscribe';
    }

    protected static function linkLifetime(): int
    {
        return 60 * 24 * 30;
    }

    protected static function linkHashOf(User $account): string
    {
        return sha1($account->getEmailForVerification());
    }

    protected function invalidLinkMessage(): string
    {
        return 'The unsubscribe link is invalid or has expired.';
    }
}
```

The email builds the link with `UnsubscribeRequest::linkParameters($user)` as its query, and the controller reads
`$request->account()`. `AcceptInvitationRequest` binds its links to the email and the password hash, so a link works
once: accepting sets the password, which changes the hash.

## WithAssignableRoles

For requests that give a user `roles`. `rolesRules()` returns the rules of `roles` (an optional array) and `roles.*`
(distinct names among the roles the signed-in user may assign, which `User::availableRoles()` returns: the user's own
roles and the lower ones):

```php
public function rules(): array
{
    return [
        'name' => ['sometimes', 'string', 'max:255'],
        ...$this->rolesRules(),
    ];
}
```

The trait only validates the names; the controller gives the roles. `InviteUserRequest` and `UpdateUserRequest` use it.

## Related

- [CRUD requests](01-crud-requests.md)
- [Targets](02-targets.md)
- [Base request](00-base-request.md)
- [Email verification](../../auth/09-email-verification.md)
- [Invitations](../../auth/11-invitations.md)
- [Managing users](../../users/01-managing-users.md)
- [Ranking roles](../../roles/03-ranking-roles.md)
