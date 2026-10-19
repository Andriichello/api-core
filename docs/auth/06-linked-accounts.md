# Linked accounts

A person may have an account in several organizations. api-core links those accounts to one main account by their
verified email, so the person signs in once, has one password, and switches between the accounts. This document
explains how accounts are linked and unlinked.

## Persons and accounts

Every account (`Core\Models\User`) belongs to at most one organization; working in another organization takes another
account (see [Organizations](../organizations/00-overview.md)). An email is unique among the accounts of an
organization, and among the accounts without one, deleted accounts included, so a person has at most one account in
each.

A person is a main account plus the accounts linked to it through `users.main_account_id`:

- a main account has `main_account_id` `null`;
- a linked account has the id of its main account, which is always a main account itself, so links never form chains.

| Member                                      | Description                                                              |
|---------------------------------------------|--------------------------------------------------------------------------|
| `isMainAccount()`                           | Whether the account is a main account                                    |
| `mainAccountId()`                           | The id of the person's main account (the account's own id for a main one) |
| `isSamePerson($user)`                       | Whether both accounts belong to the same person                          |
| `mainAccount()`                             | The `BelongsTo` relation to the main account                             |
| `relatedAccounts()`                         | The `HasMany` relation from a main account to its linked accounts        |
| `unlinkFromPerson()`                        | Unlink the account, as an email change does; the caller saves it         |
| `User::query()->ofPerson($user)`            | All accounts of the person (`ofPeople(...$users)` for several people)    |

`main_account_id` isn't mass-assignable and is hidden when a user is serialized, except in [`MeResource`](04-me.md).

## Linking by verified email

`Core\Models\Traits\LinksAccountsByEmail` links an account when it is saved, if:

- it is being created, or its `email_verified_at` is being set;
- its email is verified;
- it is a main account without linked accounts of its own;
- and no `main_account_id` is given explicitly in the same save.

It is then linked to the main account of the oldest other account with the same verified email. Emails are stored in
lowercase, so they match regardless of case. In practice, an account is linked when its email is verified with
[`verify-email`](09-email-verification.md), when an [invitation](11-invitations.md) is accepted, or when it is created
with a verified email.

The email must be verified on both accounts, because a verification proves that the person controls the address. An
unverified email proves nothing: anyone can type any address, an inviter included. So set `email_verified_at` only
when the person has confirmed the address. Setting it in an import or a seeder links every account with that email and
gives them one password.

A linked account takes the person's password (see [Passwords](07-passwords.md)). `verify-email` deletes its tokens
then, as they were issued with its old password.

For example, Ann has a verified account in Acme. An admin of Globex invites `ann@example.com`, which makes an account
with an unverified email, so it isn't linked yet. Ann accepts the invitation, which verifies the email and links the
Globex account to her Acme account. Her login now signs in to the Acme account, from which she
[switches](05-switching-accounts.md) to Globex.

## Unlinking and handing over

- **Email change.** An account whose email changes is unlinked, and its email counts as unverified, unless it is
  verified in the same save. It is linked again by the new email once that one is verified. The old verification
  doesn't prove the new address.
- **Main account's email change.** The accounts linked to it, deleted ones included, are handed over to the oldest of
  them, which becomes their main account.
- **Permanent deletion.** Force-deleting a main account hands its linked accounts over in the same way. A soft delete
  keeps them linked, and the deleted main account keeps holding the person's password.
- **Explicitly.** `unlinkFromPerson()` unlinks an account as an email change does, and saving
  `main_account_id` `null` explicitly keeps an account unlinked even when its email is verified in the same save.

An account that becomes a main account, or is unlinked, keeps the person's password, as every account holds a copy of
it.

```php
$account->unlinkFromPerson();
$account->save();
```

## Related

- [Switching accounts](05-switching-accounts.md)
- [Passwords](07-passwords.md)
- [Email verification](09-email-verification.md)
- [Login](01-login.md)
- [The User model](../users/00-user-model.md)
- [Re-inviting deleted users](../users/02-re-inviting-deleted-users.md)
- [Organizations](../organizations/00-overview.md)
