---
name: api-core-development
description: Build on andriichello/api-core (namespace Core\), the foundation of this API. Use when adding a model with CRUD endpoints, or working with organizations, roles and permissions, filters, sorts, includes, pagination, authentication, users, invitations, files, settings, the audit log, locked records, status transitions, sequences, jobs, notifications or locales, and whenever code extends or calls a Core\ class or changes config/api-core.php.
---

# api-core development

api-core's documentation is installed with it, in `vendor/andriichello/api-core/docs/`. Find the documents of the task
below, or in `vendor/andriichello/api-core/docs/00-index.md`, and read them before writing code. They describe the
behavior, the traps and complete examples; the code in `vendor/andriichello/api-core/app/` is the final word.

## Adding a model with endpoints

Follow `vendor/andriichello/api-core/docs/getting-started/06-building-a-feature.md` and
`vendor/andriichello/api-core/docs/getting-started/07-building-a-feature-endpoints.md`, which build every piece of an
example `Invoice`:

1. A migration with `organization_id` (`constrained()->cascadeOnDelete()`) for a model of an organization.
2. A model extending `BaseModel`, with `BelongsToOrganization` (and `organization_id` left out of `$fillable`), its
   query returned by `newEloquentBuilder()`, and an entry in the morph map through
   `MorphServiceProvider::getMorphMap()`.
3. A query extending `BaseQuery`, whose `index($user)` limits the records a user may list.
4. A repository extending `CrudRepository`: `$model`, `$resourceClass` and `$defaultSorts`.
5. A policy extending `CrudPolicy` with `$model`, and the model's permissions in the roles of `config/api-core.php`;
   then `php artisan db:seed --class="Core\Database\Seeders\RolesAndPermissionsSeeder"`.
6. Requests extending `StoreRequest` and `UpdateRequest` of `Core\Http\Requests\Crud`, whose rules scope related
   records with `$this->orgId()`.
7. A resource extending `BaseResource`, which maps relations in `$allowedIncludes` to their resources, and a
   collection extending `BaseCollection`.
8. A controller extending `CrudController` with `$resourceClass`, `$collectionClass`, `$operations`, and a constructor
   naming the concrete policy and repository.
9. Routes with an `{id}` parameter under `auth:sanctum`, `password.updated` and `organization`, named after the URI
   and operation.
10. Tests made with api-core's factories (`User::factory()`, `Organization::factory()` and their states).

## Where to read

| Task                                             | Documents                                                                              |
|--------------------------------------------------|----------------------------------------------------------------------------------------|
| The layers and how a request flows through them  | `vendor/andriichello/api-core/docs/getting-started/00-overview.md`                      |
| Config keys and env variables                    | `vendor/andriichello/api-core/docs/getting-started/03-configuration.md`                |
| Scheduling the pruning of tokens and audit logs  | `vendor/andriichello/api-core/docs/getting-started/05-scheduled-tasks.md`              |
| Updating api-core or deploying                   | `vendor/andriichello/api-core/docs/getting-started/08-upgrading.md`                    |
| Login, tokens, sessions, the signed-in user      | `vendor/andriichello/api-core/docs/auth/00-overview.md`                                |
| Switching to and linking accounts                | `vendor/andriichello/api-core/docs/auth/05-switching-accounts.md`, `vendor/andriichello/api-core/docs/auth/06-linked-accounts.md` |
| Passwords, resets, email verification            | `vendor/andriichello/api-core/docs/auth/07-passwords.md`, `vendor/andriichello/api-core/docs/auth/09-email-verification.md` |
| Inviting and managing users                      | `vendor/andriichello/api-core/docs/auth/11-invitations.md`, `vendor/andriichello/api-core/docs/users/01-managing-users.md` |
| Models of an organization                        | `vendor/andriichello/api-core/docs/organizations/03-organization-scoped-models.md`     |
| The current organization, super admins acting in all | `vendor/andriichello/api-core/docs/organizations/01-current-organization.md`, `vendor/andriichello/api-core/docs/organizations/04-acting-organization.md` |
| Roles and permissions                            | `vendor/andriichello/api-core/docs/roles/01-configuring-roles.md`, `vendor/andriichello/api-core/docs/roles/03-ranking-roles.md` |
| Which records a user may see                     | `vendor/andriichello/api-core/docs/queries/01-index-scopes.md`                         |
| Controllers, their operations and routes         | `vendor/andriichello/api-core/docs/http/controllers/01-crud-controller.md`             |
| Routes nested under parent models                | `vendor/andriichello/api-core/docs/http/controllers/02-nested-routes.md`, `vendor/andriichello/api-core/docs/http/controllers/05-nesting-under-a-parent.md` |
| Restoring deleted records                        | `vendor/andriichello/api-core/docs/http/controllers/03-restoring.md`                   |
| Operations beyond CRUD                           | `vendor/andriichello/api-core/docs/http/controllers/04-custom-operations.md`           |
| Requests, validation, normalizing input          | `vendor/andriichello/api-core/docs/http/requests/00-base-request.md`, `vendor/andriichello/api-core/docs/http/requests/01-crud-requests.md` |
| Policies and abilities                           | `vendor/andriichello/api-core/docs/http/policies/01-crud-policy.md`                    |
| Resources, includes and appends                  | `vendor/andriichello/api-core/docs/http/resources/00-base-resource.md`, `vendor/andriichello/api-core/docs/http/resources/02-includes.md` |
| Responses and errors                             | `vendor/andriichello/api-core/docs/http/01-responses.md`, `vendor/andriichello/api-core/docs/http/02-errors.md` |
| Middleware and their order                       | `vendor/andriichello/api-core/docs/http/03-middleware.md`                              |
| Filters, sorts and pagination                    | `vendor/andriichello/api-core/docs/querying/00-filters.md`, `vendor/andriichello/api-core/docs/querying/04-sorting.md`, `vendor/andriichello/api-core/docs/pagination/00-paginating-lists.md` |
| Repositories                                     | `vendor/andriichello/api-core/docs/repositories/01-crud-repository.md`                 |
| Files of models                                  | `vendor/andriichello/api-core/docs/files/00-overview.md`, `vendor/andriichello/api-core/docs/files/02-file-routes.md` |
| Settings of models                               | `vendor/andriichello/api-core/docs/settings/00-defining-settings.md`, `vendor/andriichello/api-core/docs/settings/02-setting-routes.md` |
| Locked records and status transitions            | `vendor/andriichello/api-core/docs/models/02-locked-records.md`, `vendor/andriichello/api-core/docs/models/03-status-transitions.md` |
| Gapless numbers                                  | `vendor/andriichello/api-core/docs/models/04-sequences.md`                             |
| Recording who created a record                   | `vendor/andriichello/api-core/docs/models/01-tracking-creator.md`                      |
| The audit log                                    | `vendor/andriichello/api-core/docs/audit/00-overview.md`                               |
| Queued jobs in an organization                   | `vendor/andriichello/api-core/docs/jobs/00-async-jobs.md`                              |
| Notifications                                    | `vendor/andriichello/api-core/docs/notifications/00-notifications.md`                  |
| Locales and translations                         | `vendor/andriichello/api-core/docs/localization/00-locale-middleware.md`, `vendor/andriichello/api-core/docs/localization/01-translations.md` |
| Tables, factories and seeders                    | `vendor/andriichello/api-core/docs/database/00-migrations.md`, `vendor/andriichello/api-core/docs/database/01-factories-and-seeders.md` |

## Traps

- An account belongs to one organization. A person in another organization has another account, linked to the first
  through `main_account_id`; there is no membership pivot.
- A relation in `$allowedIncludes` without a resource (`['client']` rather than `['client' => ClientResource::class]`)
  allows no includes or appends nested under it: those are 422s.
- A `FileController`, a `SettingController` or a controller using `NestsUnderParent` needs the policy of its parent
  model, or every request fails with a `LogicException`.
- Jobs that touch records of an organization extend `AsyncJob` or `SyncJob` and are dispatched with
  `dispatchFor($organization, ...)`, so they run in that organization.
- Allocate numbers with `Sequence::next()` (or `nextInSequence()`) inside the transaction that saves the numbered
  record, so a rollback gives the number back.
- Query updates and `Model::withoutEvents()` (`saveQuietly()` and the like) bypass locked records and status
  transitions.
- Outside debug mode, a missing model is a plain 404 with `Not found.`, without the class or the id.
