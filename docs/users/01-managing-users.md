# Managing users

`Core\Http\Controllers\UserController` lets the managers of an organization (users whose roles have permissions on
users) list, invite, update and delete its users. Register its routes when the application manages users through the
API.

## Setup

Register the routes in `routes/api.php`. They add `auth:sanctum` and the `organization` middleware themselves, so
`UserController::routes();` alone is enough. Other middleware, such as `password.updated`, go on a group around
them:

```php
use Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('password.updated')->group(fn () => UserController::routes());
```

Invited people accept their invitation through the auth routes (see [Invitations](../auth/11-invitations.md)).

## Routes

| Method   | Path                          | Name               | Description                                        |
|----------|-------------------------------|--------------------|----------------------------------------------------|
| `GET`    | `/api/users`                  | `users.index`      | Lists the users, a page at a time.                 |
| `POST`   | `/api/users`                  | `users.store`      | Invites a person to a new account.                 |
| `GET`    | `/api/users/{id}`             | `users.show`       | Shows a user.                                      |
| `PATCH`  | `/api/users/{id}`             | `users.update`     | Updates a user's name, timezone, locale and roles. |
| `DELETE` | `/api/users/{id}`             | `users.destroy`    | Deletes a user (`force` deletes permanently).      |
| `POST`   | `/api/users/{id}/invitation`  | `users.invitation` | Emails the invitation again.                       |

Each route needs the permission of its ability on `models.users`: `viewAny`, `create`, `view`, `update`, `delete`
(or `forceDelete` with `force`) and `update`. On top of the permissions, `UserPolicy` keeps a manager off users who
rank above them and off deleting themselves (see [User policy](../http/policies/03-user-policy.md)).

## Which users are reached

The routes act in the organization of the request (see
[Acting organization](../organizations/04-acting-organization.md)), and every one of them finds users through
`UserQuery::index()`: the users of that organization and the signed-in person's own accounts in any organization; all
users for a super admin acting in all organizations. Any other id answers `404` (`Not found.` outside debug mode).
Since the person's own accounts are in that scope, the list and `show` include them, but changing, deleting or
inviting again a user of another organization than the acting one answers `403`: a person's accounts elsewhere are
managed only from their own organizations, with the roles held there (see
[User policy](../http/policies/03-user-policy.md)).

## Listing and showing

The list takes the `filter`, `sort` and `page` query parameters. Each user comes with the names of their roles; the
password is never included. No includes are allowed, so `?include=organization` answers `422`.

```http
GET /api/users?filter[email][like]=bob%25&sort=-created_at&page[size]=2 HTTP/1.1
```

```json
{
    "data": [
        {
            "id": 2,
            "organization_id": 1,
            "name": "Bob",
            "email": "bob@example.com",
            "timezone": "UTC",
            "locale": "en",
            "metadata": null,
            "email_verified_at": "2026-10-20T09:00:00.000000Z",
            "suspended_at": null,
            "last_login_at": null,
            "created_at": "2026-10-20T09:00:00.000000Z",
            "updated_at": "2026-10-20T09:00:00.000000Z",
            "deleted_at": null,
            "password_updated_at": "2026-10-20T09:00:00.000000Z",
            "roles": ["user"]
        }
    ],
    "meta": {"from": 1, "to": 1, "path": "http://localhost/api/users", "per_page": 2, "current_page": 1,
        "last_page": 1, "total": 1, "links": {"first": "...", "prev": null, "self": "...", "next": null, "last": "..."}},
    "message": "OK"
}
```

`GET /api/users/2` answers `{"data": {...}, "message": "OK"}` with the same user.

## Inviting

```http
POST /api/users HTTP/1.1
Content-Type: application/json

{"name": "Ann", "email": "Ann@Example.com", "roles": ["user"]}
```

| Field      | Rules                                                                                          |
|------------|------------------------------------------------------------------------------------------------|
| `name`     | Required string, up to 255 characters.                                                         |
| `email`    | Required email, up to 255 characters, lowercased; not used by another user of the organization. |
| `timezone` | Optional, a valid timezone.                                                                    |
| `locale`   | Optional, one of `api-core.locales`; the invitation is emailed in it.                          |
| `roles`    | Optional list of distinct role names, which the manager may give (see below).                  |

A `password` is ignored: the person chooses it when accepting the invitation, so nobody else knows it. The account is
created in the organization of the request with a random password and an unverified email, so it can't sign in
until the invitation is accepted, and the person is emailed the invitation, naming the manager as the inviter.

```json
{
    "data": {
        "name": "Ann",
        "email": "ann@example.com",
        "organization_id": 1,
        "email_verified_at": null,
        "password_updated_at": null,
        "updated_at": "2026-10-20T09:00:00.000000Z",
        "created_at": "2026-10-20T09:00:00.000000Z",
        "id": 3,
        "roles": ["user"]
    },
    "message": "Invited"
}
```

The status is `201`. An email used by another organization's account is fine; the email of a deleted user of this
organization restores that user instead (see [Re-inviting deleted users](02-re-inviting-deleted-users.md)).

## Updating

`PATCH /api/users/{id}` takes `name`, `timezone`, `locale` and `roles`, and answers the user with `Updated`. Nobody
changes another person's email or password: the password is shared by all of the person's accounts, so only they set
it, and the email proves who they are. A body with none of the allowed fields is refused:

```json
{
    "message": "None of the given fields can be updated: email.",
    "errors": {"fields": ["None of the given fields can be updated: email."]}
}
```

## Roles a manager may give

- `roles` may hold only the manager's `availableRoles()`: their own roles and the lower ones (see
  [Ranking roles](../roles/03-ranking-roles.md)). Others fail validation (`The selected roles.0 is invalid.`).
- Adding a role needs the `attachRole models.users` permission, and removing one `detachRole models.users`.
- `roles` replaces the roles the manager may give; the user's other roles stay. If Bob has `user` and `billing`, which
  the admin may not give, `{"roles": ["guest"]}` leaves him with `guest` and `billing`.
- Without `roles`, an update leaves the roles as they are.

## Deleting

`DELETE /api/users/{id}` soft-deletes the user and answers `{"message": "Deleted"}`. The account can no longer sign
in, and its tokens stop working. `DELETE /api/users/{id}?force=1` deletes it permanently, which needs
`forceDelete models.users` (by default, only super admins have it); the accounts linked to it are handed over to the
oldest of them (see [Linked accounts](../auth/06-linked-accounts.md)). Nobody may delete themselves.

## Resending the invitation

`POST /api/users/{id}/invitation` emails the invitation again and answers `{"message": "Invitation sent"}`. It needs
the ability to update the user. Once the invitation is accepted (the email is verified), it answers `409`.

## Errors

| Status | Message                                                  | When                                                      |
|--------|----------------------------------------------------------|-----------------------------------------------------------|
| `403`  | `This action is unauthorized.`                           | A permission is missing, the user ranks above the manager, or managers delete themselves. |
| `403`  | `You are not authorized to change the roles of users.`   | Adding roles without `attachRole`, or removing them without `detachRole`. |
| `403`  | `You are not authorized to invite this user again.`      | The deleted user of the email ranks above the manager.    |
| `404`  | `Not found.`                                             | The user isn't among the users the manager reaches.       |
| `409`  | `The invitation has been accepted already.`              | Resending an accepted invitation.                         |
| `422`  | Validation errors                                        | The email is taken, a role may not be given, or no field can be updated. |

## Related

- [The User model](00-user-model.md)
- [Re-inviting deleted users](02-re-inviting-deleted-users.md)
- [Invitations](../auth/11-invitations.md)
- [User policy](../http/policies/03-user-policy.md)
- [Request traits](../http/requests/03-request-traits.md)
- [Ranking roles](../roles/03-ranking-roles.md)
- [Configuring roles](../roles/01-configuring-roles.md)
- [Acting organization](../organizations/04-acting-organization.md)
- [Filters](../querying/00-filters.md)
- [Paginated responses](../pagination/01-paginated-responses.md)
