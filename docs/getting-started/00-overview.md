# Overview

api-core is a Laravel 13 package (`andriichello/api-core`, namespace `Core\`) that gives a multi-tenant JSON API its
foundation. Read this page first to see what it provides, how its layers fit together and where each feature is
documented.

## What it provides

- **Accounts and sign-in**: Sanctum access and refresh tokens, or sessions for a SPA; accounts of one person in several
  organizations, linked and sharing one password; switching between them; password reset, email verification and
  invitations.
- **Tenancy**: one organization per account, a current organization per request, and models scoped to it.
- **Authorization**: roles and their permissions in config (on top of spatie/laravel-permission), ranked roles, and
  policies of CRUD operations.
- **CRUD**: a controller, requests, a repository and resources, which turn a model into list, show, create, update,
  delete and restore endpoints with the `filter`, `sort`, `include`, `append` and `page` query parameters.
- **Model building blocks**: files, settings, an audit log, locked records, status transitions, gapless sequences and
  tracking of the creator.
- **Plumbing**: JSON responses on API paths, plain 404s, a locale per request, notifications, and queued jobs that run
  in an organization.

api-core registers no routes. An application registers them with the `routes()` methods of api-core's controllers
(`AuthController`, `UserController`, `NotificationController`) and with its own controllers built on `CrudController`.

## The layers

Each layer of a CRUD endpoint is a base class, which the application extends for its model:

| Layer      | Base class                                                | Responsibility                                                                  |
|------------|-----------------------------------------------------------|---------------------------------------------------------------------------------|
| Model      | `Core\Models\BaseModel`                                   | an Eloquent model with `orgId()`, whose builder is a `BaseQuery`                |
| Query      | `Core\Queries\BaseQuery`                                  | `index($user, $params)`: the records a user may list; query helpers             |
| Repository | `Core\Repositories\CrudRepository`                        | applies `index()`, filters, sorts and includes; creates, updates, deletes       |
| Request    | `Core\Http\Requests\CrudRequest` and its subclasses       | the ability to check, the validation rules and the target record                |
| Policy     | `Core\Policies\CrudPolicy`                                | the `{ability} {morph}` permission, then the ability's own rule                 |
| Controller | `Core\Http\Controllers\CrudController`                    | maps operations to requests and handles them                                    |
| Resource   | `Core\Http\Resources\BaseResource`, `BaseCollection`      | turns models into JSON, with the requested includes and appends                 |
| Response   | `Core\Http\Responses\ApiResponse`                         | the `{"data": …, "message": …}` envelope                                        |

A call to a CRUD route goes through them in this order:

1. The route's middleware authenticate the user (`auth:sanctum`) and set the current organization (`organization`).
2. `CrudController` makes the operation's request (from `$operations`) out of the incoming one and finds the parent
   models, which the route names (`$parents`).
3. `CrudPolicy::determine()` authorizes it: 403 when refused, and 404 when the target record isn't one the user may
   list, as the target is looked up through `index()`.
4. The request is validated: 422 on failure.
5. `handle{Operation}()` builds the query through the repository, loads or changes the model, and wraps the result in
   the controller's resource or collection.
6. `ApiResponse` answers with the data and a message (`OK`, `Created`, `Updated`, `Deleted`, `Restored`), and a list
   adds the `meta` of its page.

[Building a feature](06-building-a-feature.md) walks through all of them with an example.

## Service providers

Composer's package discovery registers the three providers (`extra.laravel.providers`), so an application doesn't list
them.

| Provider                                | What it does                                                                                                                                                                                                                                                                                          |
|-----------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Core\Providers\CoreServiceProvider`    | Merges the `api-core` and `pagination` config; publishes the config (`api-core-config` tag) and the migrations (`api-core-migrations`); registers the `organization`, `audit` and `locale` middleware aliases and ranks `organization` and `locale` right after authentication; adds the global `ForceJsonOnApi`; turns missing models and enum cases into a plain `Not found.` 404 outside debug mode. |
| `Core\Providers\AuthServiceProvider`    | Makes `Core\Models\AccessToken` Sanctum's token model; replaces Sanctum's `AuthenticateSession`; registers the `password.updated` alias; refuses tokens of users who can no longer sign in; records sign-ins and sign-outs in the audit log when enabled; defines the `api-core-auth` rate limiter; points the password reset and email verification links to the SPA. |
| `Core\Providers\MorphServiceProvider`   | Adds api-core's models to the morph map as `models.access-tokens`, `models.audit-logs`, `models.files`, `models.organizations`, `models.sequences`, `models.settings` and `models.users`.                                                                                                              |

## Namespaces

| Namespace                    | Directory            | Contents                                                          |
|------------------------------|----------------------|-------------------------------------------------------------------|
| `Core\Models`                | `app/Models`         | models, their traits (`Traits`) and interfaces (`Interfaces`)     |
| `Core\Queries`               | `app/Queries`        | query builders of the models                                      |
| `Core\Repositories`          | `app/Repositories`   | repositories and their traits (filters, sorts, eager loads)       |
| `Core\Http\Controllers`      | `app/Http/Controllers` | base, CRUD, auth, user, file, setting and notification controllers |
| `Core\Http\Requests`         | `app/Http/Requests`  | `BaseRequest`, CRUD requests (`Crud`), auth, user, file and setting requests |
| `Core\Http\Resources`        | `app/Http/Resources` | resources, collections and `ResourcePaginator`                    |
| `Core\Http\Middleware`       | `app/Http/Middleware` | the middleware api-core registers                                |
| `Core\Http\Filters`          | `app/Http/Filters`   | filters of the `filter` query parameter                           |
| `Core\Policies`              | `app/Policies`       | `BasePolicy`, `CrudPolicy`, `UserPolicy` and their traits         |
| `Core\Enums`                 | `app/Enums`          | `UserRole`, `OrgContext`, `FilterOperation`, `FilterFlag`         |
| `Core\Exceptions`            | `app/Exceptions`     | `RecordLockedException`, `StatusTransitionException`              |
| `Core\Helpers`               | `app/Helpers`        | `CurrentOrganization`, `Helpers`, `FilterHelper`                  |
| `Core\Jobs`                  | `app/Jobs`           | `AsyncJob`, `SyncJob`                                             |
| `Core\Notifications`         | `app/Notifications`  | `InvitationNotification`                                          |
| `Core\Database\Factories`    | `database/factories` | model factories                                                   |
| `Core\Database\Seeders`      | `database/seeders`   | `RolesAndPermissionsSeeder`                                       |

## Feature map

| Feature                         | Start with                                                                    |
|---------------------------------|-------------------------------------------------------------------------------|
| Installing and configuring      | [Installation](02-installation.md), [Configuration](03-configuration.md)      |
| Signing in, tokens and sessions | [Authentication](../auth/00-overview.md), [SPA with sessions](04-spa.md)      |
| Linked accounts and passwords   | [Linked accounts](../auth/06-linked-accounts.md), [Passwords](../auth/07-passwords.md) |
| Invitations                     | [Invitations](../auth/11-invitations.md)                                      |
| Organizations (tenancy)         | [Organizations](../organizations/00-overview.md), [Organization-scoped models](../organizations/03-organization-scoped-models.md) |
| Roles and permissions           | [Roles](../roles/00-overview.md), [Configuring roles](../roles/01-configuring-roles.md) |
| Users of an organization        | [User model](../users/00-user-model.md), [Managing users](../users/01-managing-users.md) |
| HTTP layer                      | [HTTP overview](../http/00-overview.md), [Responses](../http/01-responses.md), [Errors](../http/02-errors.md) |
| CRUD controllers                | [CrudController](../http/controllers/01-crud-controller.md), [Requests](../http/requests/01-crud-requests.md), [Policies](../http/policies/01-crud-policy.md) |
| Resources, includes and appends | [Base resource](../http/resources/00-base-resource.md), [Includes](../http/resources/02-includes.md) |
| Pagination                      | [Paginating lists](../pagination/00-paginating-lists.md)                      |
| Filters and sorts               | [Filters](../querying/00-filters.md), [Sorting](../querying/04-sorting.md)    |
| Models, queries, repositories   | [Base model](../models/00-base-model.md), [Base query](../queries/00-base-query.md), [CRUD repository](../repositories/01-crud-repository.md) |
| Locked records and statuses     | [Locked records](../models/02-locked-records.md), [Status transitions](../models/03-status-transitions.md) |
| Sequences                       | [Sequences](../models/04-sequences.md)                                        |
| Files                           | [Files](../files/00-overview.md)                                              |
| Settings                        | [Defining settings](../settings/00-defining-settings.md)                      |
| Audit log                       | [Audit log](../audit/00-overview.md)                                          |
| Notifications                   | [Notifications](../notifications/00-notifications.md)                         |
| Jobs                            | [Async jobs](../jobs/00-async-jobs.md)                                        |
| Locales                         | [Locale middleware](../localization/00-locale-middleware.md)                  |
| Database tables                 | [Migrations](../database/00-migrations.md), [Factories and seeders](../database/01-factories-and-seeders.md) |
| Working on api-core itself      | [Development setup](../development/00-setup.md)                               |

## Related

- [Requirements](01-requirements.md)
- [Installation](02-installation.md)
- [Building a feature](06-building-a-feature.md)
- [HTTP overview](../http/00-overview.md)
- [Middleware](../http/03-middleware.md)
