# api-core

This application is built on `andriichello/api-core` (namespace `Core\`): authentication with tokens or sessions,
organizations (tenancy), roles, CRUD controllers with filters, sorts, includes and pagination, files, settings, an
audit log, locked records, status transitions and sequences.

## Documentation

- api-core's documentation is in `vendor/andriichello/api-core/docs/`, listed in
  `vendor/andriichello/api-core/docs/00-index.md`. Read the documents of a feature before building on it or changing
  how the application uses it. Where they leave a question open, read the code in `vendor/andriichello/api-core/app/`.
- Activate the `api-core-development` skill when adding a model with endpoints or using any of api-core's features.
- Before updating api-core, read `vendor/andriichello/api-core/docs/getting-started/08-upgrading.md`.

## Rules

- Build endpoints from api-core's base classes (`BaseModel`, `BaseQuery`, `CrudRepository`, `CrudPolicy`, the requests
  of `Core\Http\Requests\Crud`, `BaseResource`, `BaseCollection`, `CrudController`), as
  `vendor/andriichello/api-core/docs/getting-started/06-building-a-feature.md` does, not from plain Laravel ones.
- A query's `index($user)` decides which records a user may see: lists, the records of show, update and delete (others
  are 404s) and included records all go through it.
- Models of an organization use `BelongsToOrganization`, which finds nothing without a current organization, so their
  routes need the `organization` middleware. Take a request's organization from `orgId()`, never from the input.
- Normalize request input in `defaults()` and `overrides()`, not in `prepareForValidation()`, which runs too late.
- Permissions are configured in `api-core.roles`. The application's list replaces api-core's as a whole, and `all`
  leaves out `restore` and `forceDelete`. Run `RolesAndPermissionsSeeder` after changing it.
- Prune expired tokens with `php artisan model:prune --model="Core\Models\AccessToken"`, never with
  `sanctum:prune-expired`, which deletes refresh tokens that are still valid.
