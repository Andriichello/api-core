# Required password update

When anyone but the person sets a password (an admin tool, a seeder, an import, the random password of an
invitation), the person must replace it before using the application. The `password.updated` middleware enforces this
on the routes it is put on.

## How it is tracked

`users.password_updated_at` holds when the person last set their password themselves, or `null` when someone else set
it:

- `User` clears `password_updated_at` whenever `password` is saved without `password_updated_at` in the same save.
- The code that lets the person set the password stamps it along: [`change-password`](07-passwords.md),
  [`reset-password`](08-password-reset.md), [`accept-invitation`](11-invitations.md) with a password, and
  `UserRepository::updatePassword()`.
- It is shared with the password across the person's [linked accounts](06-linked-accounts.md), and read from the main
  account: `User::passwordUpdatedAt()` and `User::isPasswordUpdateRequired()`.

[`MeResource`](04-me.md) shows both, as `password_updated_at` and `is_password_update_required`.

## The middleware

`Core\Http\Middleware\RequireUpdatedPassword`, which `AuthServiceProvider` registers under the alias `password.updated`
(`RequireUpdatedPassword::ALIAS`), answers `403` while the password must be replaced:

```json
{
    "message": "Password update is required to use your account."
}
```

The message is `RequireUpdatedPassword::MESSAGE`, so a client can tell this `403` from others. Put the middleware on the
application's own routes, after `auth:sanctum`, as it checks the signed-in user and lets guests through:

```php
// routes/api.php
use App\Http\Controllers\ProjectController;
use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(fn () => AuthController::routes());

Route::middleware(['auth:sanctum', 'password.updated'])->group(function () {
    Route::get('projects', [ProjectController::class, 'index']);

    UserController::routes();
});
```

Leave it off the auth routes: the person needs `login`, `me` and `change-password` to set the new password.

## Switched accounts

When a user has [switched](05-switching-accounts.md) to the account, the middleware checks the password of the user who
switched, as it is that person who is behind the request. So a super admin may work in an account whose password must
be replaced, and a person who must replace their own password can't get around it by switching to their linked account.

## The client's flow

1. The login succeeds, and its `user` has `is_password_update_required: true`.
2. The client asks for a new password and sends it with the current one to `change-password`. The new password must
   differ from the current one, so the password someone else set can't be kept.
3. Until then, the routes with `password.updated` answer `403`, while `me` and the other auth routes work.

A [password reset](08-password-reset.md) lifts the requirement as well.

## In tests

`User::factory()` makes users whose password counts as set by them. The `requiringPasswordUpdate()` state makes one
whose password must be replaced:

```php
$user = User::factory()->requiringPasswordUpdate()->create();

$this->assertTrue($user->isPasswordUpdateRequired());
```

## Related

- [Passwords](07-passwords.md)
- [The signed-in user](04-me.md)
- [Switching accounts](05-switching-accounts.md)
- [Invitations](11-invitations.md)
- [Middleware](../http/03-middleware.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
