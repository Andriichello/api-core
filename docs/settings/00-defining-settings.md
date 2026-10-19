# Defining settings

The `HasSettings` trait gives a model settings, such as how a client's invoices are numbered: the model defines each
setting with a default, validation rules and a cast, and only the values, which differ from the defaults, are stored.
Use it for preferences of a model, which users change through the API.

## Setup

A model with settings uses `HasSettings`, implements `HasSettingsInterface` and defines its settings in
`settingDefinitions()`:

```php
namespace App\Models;

use Core\Models\BaseModel;
use Core\Models\Interfaces\HasSettingsInterface;
use Core\Models\Traits\BelongsToOrganization;
use Core\Models\Traits\HasSettings;
use Illuminate\Validation\Rule;

class Client extends BaseModel implements HasSettingsInterface
{
    use BelongsToOrganization;
    use HasSettings;

    public function settingDefinitions(): array
    {
        return [
            'invoice.numbering.strategy' => [
                'default' => 'date_sequence',
                'rules' => [Rule::in(['date_sequence', 'sequence'])],
            ],
            'invoice.payment_days' => [
                'default' => 14,
                'rules' => 'integer|min:0|max:365',
                'cast' => 'int',
            ],
            'invoice.default_project_id' => [
                'rules' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('client_id', $this->id)],
                'cast' => 'int',
            ],
        ];
    }
}
```

The settings are stored in the `settings` table, which api-core's migrations create. Add the model to the morph map, so
the stored settings name it by a stable alias (see [Base model](../models/00-base-model.md)).

## Definitions

Each definition is keyed by the setting's key and may have:

| Entry | Description |
|---|---|
| `default` | Value of the setting, while none is stored. `null` without it. |
| `rules` | Laravel validation rules of the value, as an array or a `|`-separated string. A setting without rules takes any value. |
| `cast` | `int`, `float`, `bool`, `string` or `array`: what a valid value is stored as. Without it, the value is stored as given. |

Keys are dotted, such as `invoice.numbering.strategy`, and the API nests the values by them
(`{"invoice": {"numbering": {"strategy": "sequence"}}}`). A key must not be the prefix of another key, as `invoice` and
`invoice.numbering` would be, since one value can't be both a setting and a group of settings.

The cast is applied after validation, so validate the value as what the client sends (`integer`, `boolean`), and cast
it to what the app reads. `bool` casts like PHP's `FILTER_VALIDATE_BOOLEAN` (`true`, `1`, `"1"`, `"true"`, `"on"` and
`"yes"` are true, anything else false), and `null` is never cast. An unknown cast throws an `InvalidArgumentException`,
when a value of the setting is updated.

## Why definitions are an instance method

`settingDefinitions()` belongs to the model instance, not the class, so a setting's rules can depend on the model, as
`invoice.default_project_id` above accepts only the client's own projects. Definitions are read on every read and
update of a setting, so keep the method cheap.

## The `settings` table

| Column | Description |
|---|---|
| `id` | Key. |
| `model_type`, `model_id` | The model the setting belongs to. |
| `key` | The setting's key, such as `invoice.numbering.strategy`. |
| `value` | JSON value, nullable. |
| `created_at`, `updated_at` | Timestamps. |

The pair of model and key is unique, so a model has one stored value per setting. `Core\Models\Setting` is the model of
a row, with its `model()` relation to the model.

The morph is named `model` rather than `settable`, because a `settable()` relation would collide with Eloquent's
`setTable()`: PHP method names ignore case.

A model's stored settings are deleted with it. A soft-deleted model keeps them until it is deleted permanently.

## In tests

`Setting::factory()` makes a setting of a new user with a random key and value. Store a value of the app's model with
`for()`:

```php
Setting::factory()->for($client, 'model')->create(['key' => 'invoice.payment_days', 'value' => 30]);
```

Or go through the trait, which validates the value: `$client->updateSettings(['invoice.payment_days' => 30])`.

## Related

- [Reading and updating settings](01-reading-and-updating.md)
- [Setting routes](02-setting-routes.md)
- [Base model and the morph map](../models/00-base-model.md)
- [Migrations](../database/00-migrations.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
