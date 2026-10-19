# Setting routes

`SettingController` gives the settings of a model two routes, nested under the model, such as
`clients/{client}/settings`: one shows all of them, the other updates some. An app makes one subclass per model, whose
settings users change through the API.

## Setup

The subclass names the model's class and route parameter, and takes the model's policy, which authorizes every request:

```php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Policies\ClientPolicy;
use Core\Http\Controllers\SettingController;
use Core\Repositories\SettingRepository;

class ClientSettingController extends SettingController
{
    protected string $settable = Client::class;

    protected string $parameter = 'client';

    public function __construct(ClientPolicy $policy, SettingRepository $repository)
    {
        parent::__construct($policy, $repository);
    }
}
```

As with files, declare the constructor with the model's policy: a plain `CrudPolicy`, which the container passes
without it, or a policy of another model fails every request with a `LogicException`, rather than run without the
model's permissions. `$settable` must implement `HasSettingsInterface`.

Register the routes in `routes/api.php`, under the app's auth middleware:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    ClientSettingController::routes('clients/{client}/settings');
});
```

When the model is nested deeper, such as `profiles/{profile}/clients/{client}/settings`, list the outer parameters in
`$parents` (the model's `BelongsTo` relations), as for [files](../files/02-file-routes.md#deeper-nesting).

## Routes

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/clients/{client}/settings` | All settings of the client, nested by their keys, defaults included. |
| `PATCH` | `/api/clients/{client}/settings` | Update the settings, which the body gives, and answer all of them. |

## Access

The client is found within the user's index scope, so a client the user can't list is a 404. Then the request is
authorized as an ability on the client with its policy (a 403 on denial): `GET` needs `view` and `PATCH` needs
`update`. There are no permissions of their own for settings (see
[Nesting under a parent](../http/controllers/05-nesting-under-a-parent.md)).

## Showing

```http
GET /api/clients/12/settings
Authorization: Bearer {access-token}
```

```json
{
    "data": {
        "invoice": {
            "numbering": {
                "strategy": "sequence"
            },
            "payment_days": 14,
            "default_project_id": null
        }
    },
    "message": "OK"
}
```

A model without definitions answers `"data": {}`.

## Updating

The body nests the values by the settings' keys; dotted keys work too (`{"invoice.payment_days": 30}`). Only the body
counts: query parameters are never taken for settings.

```http
PATCH /api/clients/12/settings
Authorization: Bearer {access-token}
Content-Type: application/json

{
    "invoice": {
        "payment_days": "30"
    }
}
```

The response has all the settings, after the update and its casts, with `"message": "Updated"`:

```json
{
    "data": {
        "invoice": {
            "numbering": {
                "strategy": "sequence"
            },
            "payment_days": 30,
            "default_project_id": null
        }
    },
    "message": "Updated"
}
```

## Errors

| Status | Error key | When |
|---|---|---|
| 401 | | The request isn't authenticated. |
| 403 | | The policy denies `view` or `update` on the model. |
| 404 | | The model isn't in the user's index scope. |
| 422 | the setting's key | A value fails the setting's rules, or the setting isn't defined. Nothing is stored. |
| 422 | `settings` | The body is empty: `The request has no settings to update.` |
| 422 | `body` | The body is a JSON list or a single value rather than an object. |

```json
{
    "message": "The selected invoice.numbering.strategy is invalid. (and 1 more error)",
    "errors": {
        "invoice.numbering.strategy": [
            "The selected invoice.numbering.strategy is invalid."
        ],
        "theme": [
            "The theme setting doesn't exist."
        ]
    }
}
```

## Related

- [Defining settings](00-defining-settings.md)
- [Reading and updating settings](01-reading-and-updating.md)
- [Nesting under a parent](../http/controllers/05-nesting-under-a-parent.md)
- [File routes](../files/02-file-routes.md)
- [Request traits](../http/requests/03-request-traits.md)
- [Errors](../http/02-errors.md)
- [Index scopes](../queries/01-index-scopes.md)
