# Re-inviting deleted users

Inviting the email of a deleted user restores that user's account, instead of creating a new one. Use it to bring
back someone who left the organization: `POST /api/users` with their email does it, with no separate route.

## Why it restores

- The account's history stays attached to it: its audit log entries, its notifications and the records that refer
  to it.
- The unique email index counts deleted accounts (see [The User model](00-user-model.md#unique-emails)), so a second
  account with the same email can't be created in the organization anyway.

The validation of the invitation ignores deleted accounts, so their email passes, and `UserController` then looks
for one with `UserRepository::findDeleted($email, $organizationId)`: a deleted account with the email in the
organization of the request, or among the accounts without an organization when the request acts in none. Deleted
accounts of other organizations are left alone.

## Usage

```http
POST /api/users HTTP/1.1
Content-Type: application/json

{"name": "Bob", "email": "bob@example.com", "roles": ["guest"]}
```

The answer is the same as for a new invitation, `201` with `Invited`, but carries the restored account's `id`.

## What happens

In one transaction, `UserRepository::reinvite()`:

1. unlinks the account from the person's other accounts (`unlinkFromPerson()`): it leaves its main account, and the
   accounts linked to it are handed over to the oldest of them, so they keep the person's password;
2. takes `name`, `timezone` and `locale` from the request, and keeps the email;
3. gives it a random password, which nobody knows, and an unverified email, as a new invitation does;
4. restores it;
5. gives it its new roles (see below);
6. deletes its access and refresh tokens, and the tokens with which it had switched to other accounts, since a
   restored account's old tokens would work again.

Then the invitation is emailed, naming the manager as the inviter. Once the person accepts it, the email is verified,
and the account is linked to the person's other accounts again (see [Invitations](../auth/11-invitations.md) and
[Linked accounts](../auth/06-linked-accounts.md)).

## Roles

The account gets the roles of the request, along with its old roles that the manager may not give, which stay. The
roles the manager may give are replaced, even when the request has no `roles`: a re-invited `admin`, invited without
roles by another admin, comes back without `admin`. Adding roles needs `attachRole models.users`, removing them
`detachRole models.users` (see [Managing users](01-managing-users.md#roles-a-manager-may-give)).

## Rules

- A deleted user who ranks above the manager can't be invited again by them: `403` with
  `You are not authorized to invite this user again.`, and the account stays deleted.
- Re-inviting needs only the `create models.users` permission of an invitation, not `restore`.
- `suspended_at` is kept as it was, so a suspended account stays suspended after the invitation is accepted. Clear
  it yourself when the person should sign in.
- `metadata`, `created_at` and `last_login_at` are kept too.
- Users deleted along with their organization come back when the organization is restored (see
  [Organizations](../organizations/00-overview.md#deleting-and-restoring)).

## In code

The repository's methods work without the controller, for example in a console command:

```php
use Core\Repositories\UserRepository;

$users = app(UserRepository::class);
$deleted = $users->findDeleted('bob@example.com', $organization->id);

$user = $deleted === null
    ? $users->invite(['name' => 'Bob', 'email' => 'bob@example.com'], $organization->id, ['user'])
    : $users->reinvite($deleted, ['name' => 'Bob'], ['user']);
```

`invite(array $attributes, ?int $organizationId, array $roles = [], ?User $inviter = null)` and
`reinvite(User $user, array $attributes, array $roles = [], ?User $inviter = null)` both email the invitation.
`reinvite()` gives exactly the given roles; keeping the roles a manager may not give is done by `UserController`.

## Related

- [Managing users](01-managing-users.md)
- [The User model](00-user-model.md)
- [Invitations](../auth/11-invitations.md)
- [Linked accounts](../auth/06-linked-accounts.md)
- [Ranking roles](../roles/03-ranking-roles.md)
- [User policy](../http/policies/03-user-policy.md)
- [Organizations](../organizations/00-overview.md)
