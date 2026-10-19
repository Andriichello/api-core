# Writing tests

How api-core's tests are laid out and written: the helpers of `Core\Tests\TestCase`, fixture models and routes, and
what it takes for a test to pass on MySQL as well as SQLite. Every change comes with tests of its happy paths, failures
and edge cases.

## Layout

- `tests/Unit` and `tests/Feature` mirror `app/`: `app/Models/Traits/Lockable.php` is tested in
  `tests/Unit/Models/Traits/LockableTest.php`, and controllers and middleware in `tests/Feature/Http/…`.
- Test classes extend `Core\Tests\TestCase` and use `RefreshDatabase`, when they touch the database.
- Test methods are camel case, such as `testLockedRecordCannotBeDeleted()`, and, like every member of a test
  class, have a docblock (see [Conventions](04-conventions.md)).

## Helpers of `TestCase`

| Method                                                    | Use                                                                                        |
|-----------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `uploadedFile(string $name, string $contents)`            | An upload, whose type is recognized from its contents, unlike `UploadedFile::fake()`, which takes it from the name. Its temporary file is deleted after the test. |
| `rawSql(QueryBuilder\|EloquentBuilder $query)`            | The query's SQL with its bindings, with identifiers quoted in double quotes on any database. |
| `assertSameIgnoringKeyOrder(array $expected, array $actual, string $message = '')` | Compares arrays regardless of the order of keys at any depth; the order of list items still counts. |

```php
$this->assertStringContainsString('"id" > 5', $this->rawSql($query));

$this->assertSameIgnoringKeyOrder(['title' => 'Note', 'kept' => 'value'], $entry->metadata['payload']);

$document->attachFile($this->uploadedFile('invoice', self::PDF));
```

Use `rawSql()` for every assertion on SQL, since MySQL quotes identifiers with backticks, and
`assertSameIgnoringKeyOrder()` for values read back from JSON columns, since MySQL reorders their keys.

## Fixture models, requests and routes

Tests build what they need in `setUp()`: tables with `Schema::create()`, anonymous models, requests, policies,
repositories and controllers, and their routes (see `CrudControllerTest` and `CrudControllerNestedTest`):

```php
$store = get_class(new class extends StoreRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string']];
    }
});
```

- A test, which adds models to the morph map, saves it in `setUp()` (`$this->morphMap = Relation::morphMap()`) and
  restores it in `tearDown()` with `Relation::morphMap($this->morphMap, false)`.
- Queues can't serialize anonymous classes, so a queued job of a test is a named class, such as
  `tests/Unit/Jobs/RecordingJob.php`.
- Factories make users and organizations (`User::factory()`, `Organization::factory()`), with states such as
  `unverified()` and `requiringPasswordUpdate()` (see [Factories and seeders](../database/01-factories-and-seeders.md)).

## Temporary tables on MySQL

MySQL commits the test's open transaction, when a table is created. `RefreshDatabase` then finds no transaction to roll
back, so it migrates the database again before the next test: the data is gone, but the run is slower. A test, which
relies on its transaction, such as one checking what a rollback undoes, makes its table temporary:

```php
Schema::create('documents', function (Blueprint $table) {
    $table->temporary();
    $table->id();
    $table->string('title');
    $table->timestamps();
});
```

Creating a temporary table doesn't commit, but a temporary table can't have foreign keys.

## Tests for MySQL only

A test of behavior, which SQLite doesn't have, skips itself there, as `SequenceLockTest` does for row locks:

```php
if (DB::connection()->getDriverName() !== 'mysql') {
    $this->markTestSkipped('SQLite ignores row locks, so they are tested on MySQL only.');
}
```

`SequenceLockTest` copies the default connection's config to two more connections, `first` and `second`, which work
outside the test's transaction and commit. While `first` holds a sequence's row lock in an open transaction, an
allocation through `second` (with `innodb_lock_wait_timeout = 1`) must time out on the `select … for update`. The test
rolls the connections back, deletes what they committed and purges them in `beforeApplicationDestroyed()`.

## Proving a fix

A test of a bug fix should fail without the fix. Set the fixed files aside, run the test, and bring them back:

```bash
git stash push app/Models/Traits/LockedByParent.php
php8.5 artisan test --compact tests/Unit/Models/Traits/LockableTest.php
git stash pop
git stash list
```

The last command should list nothing.

## Related

- [Running tests](01-running-tests.md)
- [Conventions](04-conventions.md)
- [Code quality](03-code-quality.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
