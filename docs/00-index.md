# api-core documentation

api-core is a Laravel 13 package that gives a multi-tenant JSON API its foundation: authentication, organizations,
roles, CRUD controllers with filters, sorts and pagination, files, settings, an audit log and more. Start with the
[overview](getting-started/00-overview.md), then [install](getting-started/02-installation.md) it and follow
[building a feature](getting-started/06-building-a-feature.md).

## Getting started

- [Overview](getting-started/00-overview.md) — what api-core provides, its layers, namespaces and a feature map.
- [Requirements](getting-started/01-requirements.md) — PHP, extensions, packages and the supported databases.
- [Installation](getting-started/02-installation.md) — installing api-core in an application, step by step, and
  its guideline and skill for AI agents.
- [Configuration](getting-started/03-configuration.md) — every config key with its env variable and default.
- [SPA with sessions](getting-started/04-spa.md) — setting up a SPA that signs in with sessions.
- [Scheduled tasks](getting-started/05-scheduled-tasks.md) — pruning expired tokens and old audit log entries.
- [Building a feature](getting-started/06-building-a-feature.md) — an example, part 1: migration, model, query,
  repository, policy and permissions.
- [Building a feature: the endpoints](getting-started/07-building-a-feature-endpoints.md) — part 2: requests,
  resources, controller, routes and responses.
- [Upgrading](getting-started/08-upgrading.md) — behavior to watch when deploying or upgrading.

## Authentication

- [Authentication](auth/00-overview.md) — tokens or sessions, the auth routes, guards and rate limits.
- [Login](auth/01-login.md) — `POST login`, choosing the account, suspended accounts, responses.
- [Access and refresh tokens](auth/02-tokens.md) — lifetimes, refreshing, logging out, pruning.
- [Sessions](auth/03-sessions.md) — session login for a SPA and what ends a session.
- [The signed-in user](auth/04-me.md) — `GET` and `PATCH` `auth/me`.
- [Switching accounts](auth/05-switching-accounts.md) — acting as a linked account, with tokens and with sessions.
- [Linked accounts](auth/06-linked-accounts.md) — persons and accounts, linking by verified email, unlinking.
- [Passwords](auth/07-passwords.md) — one password per person, `change-password`.
- [Password reset](auth/08-password-reset.md) — `forgot-password` and `reset-password`.
- [Email verification](auth/09-email-verification.md) — signed links and what a verification triggers.
- [Required password update](auth/10-required-password-update.md) — `password_updated_at` and `password.updated`.
- [Invitations](auth/11-invitations.md) — the invitation email and `accept-invitation`.

## Organizations

- [Organizations](organizations/00-overview.md) — one organization per account, the model, deleting and restoring.
- [Current organization](organizations/01-current-organization.md) — `CurrentOrganization`, Context and queued jobs.
- [Resolving the organization](organizations/02-resolving-the-organization.md) — the `organization` middleware.
- [Organization-scoped models](organizations/03-organization-scoped-models.md) — `BelongsToOrganization`, `HasOrgId`.
- [Acting organization](organizations/04-acting-organization.md) — how lists are scoped for users and super admins.

## Roles

- [Roles](roles/00-overview.md) — roles, the built-in super admin and permission names.
- [Configuring roles](roles/01-configuring-roles.md) — the `api-core.roles` list and its actions.
- [Syncing roles](roles/02-syncing-roles.md) — `RolesAndPermissionsSeeder` and `RoleRepository::sync()`.
- [Ranking roles](roles/03-ranking-roles.md) — ranks, assignable roles and role checks.

## Users

- [The User model](users/00-user-model.md) — columns, relations, suspension, unique emails, `UserQuery`.
- [Managing users](users/01-managing-users.md) — listing, inviting, updating and deleting the users of an organization.
- [Re-inviting deleted users](users/02-re-inviting-deleted-users.md) — inviting a deleted user's email restores it.

## HTTP

- [HTTP layer](http/00-overview.md) — how a request flows through api-core and what an app writes.
- [Responses](http/01-responses.md) — `ApiResponse` and the response envelope.
- [Errors](http/02-errors.md) — JSON errors on API paths, plain 404s and api-core's error responses.
- [Middleware](http/03-middleware.md) — every middleware, its alias, order and place.

### Controllers

- [Base controllers](http/controllers/00-base-controllers.md) — `BaseController` and `ResourceController`.
- [CRUD controller](http/controllers/01-crud-controller.md) — `CrudController`, its operations and routes.
- [Nested routes](http/controllers/02-nested-routes.md) — CRUD routes under parent models with `$parents`.
- [Restoring](http/controllers/03-restoring.md) — the opt-in `restore` operation.
- [Custom operations](http/controllers/04-custom-operations.md) — extra operations and overriding handlers.
- [Nesting under a parent](http/controllers/05-nesting-under-a-parent.md) — the `NestsUnderParent` trait.

### Requests

- [Base request](http/requests/00-base-request.md) — `BaseRequest`, `defaults()`/`overrides()` and `orgId()`.
- [CRUD requests](http/requests/01-crud-requests.md) — the requests of the CRUD operations.
- [Targets](http/requests/02-targets.md) — `WithTarget`: the record a request acts on.
- [Request traits](http/requests/03-request-traits.md) — `WithObjectBody`, `WithAccountLink`, `WithAssignableRoles`.

### Resources

- [Base resource](http/resources/00-base-resource.md) — `BaseResource` and writing a resource.
- [Collections](http/resources/01-collections.md) — `BaseCollection`.
- [Includes](http/resources/02-includes.md) — `?include=`, nested includes and their scoping.
- [Appends](http/resources/03-appends.md) — `?append=`.

### Policies

- [Base policy](http/policies/00-base-policy.md) — `BasePolicy` and `ChecksPermissions`.
- [CRUD policy](http/policies/01-crud-policy.md) — the abilities of CRUD operations.
- [Limiting changes](http/policies/02-limiting-cud.md) — `LimitsCUD`: changes by super admins only.
- [User policy](http/policies/03-user-policy.md) — managing users by rank.

## Lists

- [Paginating lists](pagination/00-paginating-lists.md) — `page[number]`, `page[size]` and their limits.
- [Paginated responses](pagination/01-paginated-responses.md) — the JSON of a page.
- [Paginating in code](pagination/02-paginating-in-code.md) — `WithPagination` and `chunkPaginated()`.
- [Filters](querying/00-filters.md) — the `filter` parameter and allowed filters.
- [Filter operations](querying/01-filter-operations.md) — operations and flags.
- [Date filters](querying/02-date-filters.md) — `DateFilter`.
- [Custom filters](querying/03-custom-filters.md) — writing filters.
- [Sorting](querying/04-sorting.md) — the `sort` parameter and allowed sorts.

## Data layer

- [Base model](models/00-base-model.md) — `BaseModel`, the morph map and queryable columns.
- [Tracking the creator](models/01-tracking-creator.md) — `TracksCreator`.
- [Locked records](models/02-locked-records.md) — `Lockable` and `LockedByParent`.
- [Status transitions](models/03-status-transitions.md) — `TransitionsStatus`.
- [Sequences](models/04-sequences.md) — gapless numbers under row locks.
- [Enums](models/05-enums.md) — `BaseEnum` and the enums api-core ships.
- [Base query](queries/00-base-query.md) — `BaseQuery` and its helpers.
- [Index scopes](queries/01-index-scopes.md) — `index($user)`: the records each user may list.
- [Base repository](repositories/00-base-repository.md) — `BaseRepository` and the repositories api-core ships.
- [CRUD repository](repositories/01-crud-repository.md) — `CrudRepository`: queries, create, update, delete, restore.
- [Eager loads](repositories/02-eager-loads.md) — `AllowsEagerLoads`: eager loads scoped by `index()`.
- [Migrations](database/00-migrations.md) — the tables api-core creates and the stock migrations to drop.
- [Factories and seeders](database/01-factories-and-seeders.md) — factories, states and seeders for tests.

## Features

- [Files](files/00-overview.md) — the `File` model, storage and setup.
- [Attaching files](files/01-attaching-files.md) — `HasFiles`, collections, transactions and deletion.
- [File routes](files/02-file-routes.md) — a `FileController` per fileable model.
- [Upload validation](files/03-upload-validation.md) — size and extension limits, uploading and renaming.
- [Downloads and URLs](files/04-downloads-and-urls.md) — downloads, temporary URLs and file names.
- [Defining settings](settings/00-defining-settings.md) — `HasSettings` and `settingDefinitions()`.
- [Reading and updating settings](settings/01-reading-and-updating.md) — reading, updating and resetting in code.
- [Setting routes](settings/02-setting-routes.md) — a `SettingController` per model.
- [Audit log](audit/00-overview.md) — sources, columns, immutability and config.
- [Auditing models](audit/01-auditing-models.md) — the `Audited` trait.
- [Auditing requests](audit/02-auditing-requests.md) — the `audit` middleware and its scopes.
- [Recording events](audit/03-recording-events.md) — `AuditLog::record()` and the auth events.
- [Reading and pruning the audit log](audit/04-reading-and-pruning.md) — who sees which entries, retention.
- [Notifications](notifications/00-notifications.md) — listing, counting, reading and deleting notifications.
- [Async jobs](jobs/00-async-jobs.md) — queued jobs that run in an organization.
- [Sync jobs](jobs/01-sync-jobs.md) — `SyncJob`.
- [Locale middleware](localization/00-locale-middleware.md) — the request's locale.
- [Translations](localization/01-translations.md) — what an application translates.

## Developing api-core

- [Development setup](development/00-setup.md) — PHP 8.5, the setup script and the repository layout.
- [Running tests](development/01-running-tests.md) — SQLite, parallel runs and MySQL.
- [Writing tests](development/02-writing-tests.md) — `TestCase` helpers and MySQL-only tests.
- [Code quality](development/03-code-quality.md) — GrumPHP, phpcs, phpmd, PHPStan and Pint.
- [Conventions](development/04-conventions.md) — commit messages, docblocks, code style and documents.
