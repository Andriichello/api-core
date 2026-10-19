# User policy

`Core\Policies\UserPolicy` authorizes the routes of `UserController`, through which managers handle the users of
their organization. It is a `CrudPolicy` of `User`, which adds rules of rank: nobody changes a user who ranks above
them, and nobody deletes themselves.

## Rules

Each ability needs its permission on `models.users` first, as in any [CRUD policy](01-crud-policy.md). Then:

| Ability                | Operation                    | Allowed when                                                            |
|------------------------|------------------------------|-------------------------------------------------------------------------|
| `viewAny`, `view`      | list, show                   | Always.                                                                 |
| `create`               | invite (`POST users`)        | The user has a role other than `guest`.                                 |
| `update`               | `PATCH users/{id}`, resending the invitation | As `create`, and the target doesn't rank above the user. |
| `delete`, `forceDelete`| `DELETE users/{id}`          | As `create`, the target isn't the user and doesn't rank above them.     |
| `restore`              | restoring, where a controller adds it | As `create`, and the target doesn't rank above the user.       |

A target ranks above the user when it has roles and its highest role ranks above each of the user's roles, by their
order in `api-core.roles` (`User::isHigherThan()`, see [Ranking roles](../../roles/03-ranking-roles.md)). Users of
the same rank may manage each other, and a target without roles never ranks above anybody.

Super admins skip the permissions, and nobody ranks above them, but they can't delete their own account either.

## Changing roles

`changeRoles($user, $added, $removed)` decides whether the user may give and take the roles of a user: adding needs
the `attachRole models.users` permission, and removing needs `detachRole models.users`. Super admins may always.
Which roles a manager may give at all (their own and the lower ones) is checked by the request (see
[Request traits](../requests/03-request-traits.md)).

`UserController` calls it when an invitation or an update changes roles, and answers `403` with
`You are not authorized to change the roles of users.` when it is false. The default `admin` role has
`attachRole`, `attachAnyRole` and `detachRole` on `User` (see [Configuring roles](../../roles/01-configuring-roles.md)).

## Examples

With `UserController::routes()` registered in `routes/api.php`, a manager whose role ranks below `admin` renames an
admin:

```http
PATCH /api/users/1
Content-Type: application/json

{"name": "Renamed"}
```

```json
{
    "message": "This action is unauthorized."
}
```

An admin deleting their own account gets the same `403`, while deleting a user of a lower or the same rank answers
`200` with `{"message": "Deleted"}`.

## Related

- [Managing users](../../users/01-managing-users.md)
- [CRUD policy](01-crud-policy.md)
- [Base policy](00-base-policy.md)
- [Ranking roles](../../roles/03-ranking-roles.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
- [Re-inviting deleted users](../../users/02-re-inviting-deleted-users.md)
