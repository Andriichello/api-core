# Reading and updating settings

A model with `HasSettings` reads its settings with `setting()` and `settingValues()`, which fall back to the defaults,
and changes them with `updateSettings()`, which validates them, and `resetSettings()`. Use these methods in the app's
code; the [setting routes](02-setting-routes.md) use them too.

## Reading

`setting($key)` gives one setting: its stored value, or its default, when none is stored.

```php
if ($client->setting('invoice.numbering.strategy') === 'sequence') {
    // …
}
```

A key, which isn't defined, throws an `InvalidArgumentException`, since it is a mistake in the code rather than a
missing value.

`settingValues()` gives every defined setting by its key, with the defaults filled in:

```php
$client->settingValues();
// ['invoice.numbering.strategy' => 'date_sequence', 'invoice.payment_days' => 30, 'invoice.default_project_id' => null]
```

Stored values of keys, which are no longer defined, are left out: removing a definition hides its stored values without
a migration. An unsaved model has only the defaults.

The stored settings are loaded with one query, the first time a model instance reads them, and kept on the instance
(as its `settings` relation) for later reads. `updateSettings()` and `resetSettings()` drop them, so the next read loads
them again. Another instance of the same record doesn't see changes made through this one until it reloads them, e.g.
with `fresh()` or `unsetRelation('settings')`.

## Updating

`updateSettings($values)` validates the given values with the definitions' rules, casts them and stores them. The values
may be keyed by the settings' keys, nested by them, or both:

```php
$client->updateSettings([
    'invoice' => ['numbering' => ['strategy' => 'sequence']],
    'invoice.payment_days' => '30',
]);

$client->setting('invoice.payment_days'); // 30, cast to an int
```

- Only the given settings change; the others keep their stored values or defaults.
- All of them are validated together, and nothing is stored, when any of them fails. The values are stored in one
  transaction.
- A stored `null` is a value: the setting reads `null`, not its default. Reset a setting to bring its default back.
- Updating a setting again changes its stored value; a model never has two values of one setting.
- The model must be saved first; an unsaved one throws a `LogicException`.

## Validation errors

A failure throws a `ValidationException`, whose errors are keyed by the settings' dotted keys, also for nested input. A
value of a setting, which isn't defined, fails too, also when it is nested in a group of settings, such as
`invoice.numbering.scope`:

```php
try {
    $client->updateSettings(['invoice' => ['numbering' => ['strategy' => 'random']], 'theme' => 'dark']);
} catch (ValidationException $exception) {
    $exception->errors();
    // [
    //     'invoice.numbering.strategy' => ['The selected invoice.numbering.strategy is invalid.'],
    //     'theme' => ["The theme setting doesn't exist."],
    // ]
}
```

Through a request, the exception is a 422 like any validation error (see [Setting routes](02-setting-routes.md)).

## Resetting

`resetSettings(...$keys)` deletes the stored values of the given settings, or of all of them, when no key is given, so
they read their defaults again:

```php
$client->resetSettings('invoice.numbering.strategy', 'invoice.payment_days');

$client->resetSettings();
```

It doesn't validate the keys: a key, which isn't stored, is skipped, and a key, which is no longer defined, has its
stored value deleted.

## Related

- [Defining settings](00-defining-settings.md)
- [Setting routes](02-setting-routes.md)
- [Errors](../http/02-errors.md)
