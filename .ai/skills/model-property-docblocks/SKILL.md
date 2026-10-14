---
name: model-property-docblocks
description: Generate or update @property annotations in the class DocBlock of Eloquent models. Use when creating a model, modifying a model's casts/accessors/relations, adding or changing a migration that affects a model's table, or when asked to sync/fix model docblocks.
---

# Model @property DocBlocks

## 1. Gather facts (do not guess from migrations alone)

1. Run `php artisan model:show {FQCN} --json` to get columns, nullability, casts, accessors, and relations.
2. Cross-check column types and nullability with the Boost `database-schema` tool.
3. Read the model class itself for `$casts` / `casts()`, `Attribute` accessors, legacy `get{X}Attribute` accessors,
   `$appends`, and relation method return types.
4. Use migrations only as a fallback, and account for every later migration touching the same table.

## 2. Type mapping

### Columns

The cast wins over the DB type.

| Source                                 | Type                                                     |
|----------------------------------------|----------------------------------------------------------|
| integer / bigint / unsigned            | `int`                                                    |
| `boolean` cast or tinyint(1)           | `bool`                                                   |
| float / double                         | `float`                                                  |
| `decimal:N` cast or DECIMAL column     | `string`                                                 |
| string / text / char / uuid / ulid     | `string`                                                 |
| `datetime`, `date`, and timestamps     | `Carbon`                                                 |
| `immutable_datetime`, `immutable_date` | `CarbonImmutable`                                        |
| `array` / `json` cast                  | `array`, or a precise shape when known (e.g. `string[]`) |
| `collection` cast                      | `\Illuminate\Support\Collection`                         |
| `AsArrayObject`                        | `ArrayObject`                                            |
| Enum cast                              | the enum class                                           |
| Custom cast                            | the type returned by its `get()` method                  |

A column that is nullable in the DB gets `|null` appended.

### Relations

| Relation                                                                     | Type                                                                                                                             |
|------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `BelongsTo`                                                                  | `Related`, or `Related\|null` if the FK is nullable                                                                              |
| `HasOne` / `MorphOne` / `HasOneThrough`                                      | `Related\|null`                                                                                                                  |
| `HasMany` / `BelongsToMany` / `MorphMany` / `MorphToMany` / `HasManyThrough` | `Collection<int, Related>` (`Illuminate\Database\Eloquent\Collection`)                                                           |
| `MorphTo`                                                                    | union of the concrete morph-map types when known, otherwise the shared base model (e.g. `User\|BaseModel`); `\|null` if nullable |

### Accessors / appended attributes

Use the accessor's return type.

## 3. Ordering and layout

The model DocBlock follows the class DocBlock guideline. Inside section 2 (type/structure tags), the `@property` lines
form these groups in order, one blank line between groups:

1. Table columns, in schema column order, except `created_at`, `updated_at`, `deleted_at`, which go last in that order.
2. Accessors / appended virtual attributes, alphabetical.
3. Relations, in the order their methods appear in the class.

After the `@property` groups come `@author` and `@package`, then any tool directives (`@SuppressWarnings`,
`@psalm-suppress`, etc.), per the class DocBlock guideline.

Add `use` imports for every class referenced in the DocBlock (`Carbon`, `Collection`, related models, enums). Do not use
FQCNs inline.

## 4. Example

```php
use Carbon\Carbon; // or Illuminate\Support\Carbon; be consistent
use Illuminate\Database\Eloquent\Collection;

/**
 * Class AccessToken.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $tokenable_id
 * @property string $tokenable_type
 * @property string $name
 * @property string $token
 * @property string[]|null $abilities
 * @property array|null $metadata
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property User|BaseModel $tokenable
 * @property Organization|null $organization
 * @property Collection<int, TokenUsage> $usages
 *
 * @author John Doe <john.doe@example.com>
 * @package App\Models
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
```

## 5. Update rules

- When a model already has a DocBlock, rewrite only the `@property` groups. Preserve all other tags, relocating any tool
  directives found elsewhere to directly after `@package`.
- Remove `@property` lines for columns, relations, or accessors that no longer exist.
- After editing, run `vendor/bin/pint --dirty`, and run Larastan if the project has it configured.
