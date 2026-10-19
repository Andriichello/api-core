# Files

api-core stores the files of models, such as the scans of an invoice or a generated PDF, on a disk and records each of
them as a `Core\Models\File`. Use it when a model of the app needs files that users upload and download through the API.

## How it fits together

- A model that has files uses the `HasFiles` trait and implements `HasFilesInterface`. It is the file's `fileable`
  (see [Attaching files](01-attaching-files.md)).
- Each file is a row of the `files` table plus the contents on the files disk. The row keeps the name, under which the
  file is downloaded; the disk keeps the contents under a unique path.
- The app exposes the files of a model with a `FileController` subclass, whose routes nest them under the model, such as
  `invoices/{invoice}/files` (see [File routes](02-file-routes.md)).

Files carry no `organization_id`. They are always reached through their fileable: the routes find the fileable within
the user's index scope (its query's `index($user)`) and authorize each request as an ability on it, so whoever may see
an invoice may see its files. `FileQuery::index()` itself only shuts out guests, and `FileQuery::inCollection()` limits
a query to one collection.

## Setup

1. Run api-core's migrations, which create the `files` table (see [Migrations](../database/00-migrations.md)).
2. Point `API_CORE_FILES_DISK` at a private disk. Downloads go through the API, which checks access to the fileable on
   every request; a public disk would let anyone, who knows a path, skip that check.
3. Add `HasFiles` to the models that have files, and add those models to the morph map, so `fileable_type` keeps a
   short, stable alias rather than a class name (see [Base model](../models/00-base-model.md)).
4. Write a `FileController` subclass for each of those models and register its routes.

## The `files` table

| Column | Type | Description |
|---|---|---|
| `id` | big integer | Key. |
| `fileable_type`, `fileable_id` | string, big integer | The model the file is attached to. |
| `collection` | string(64) | Group of the model's files; `default` (`File::DEFAULT_COLLECTION`) unless given. |
| `name` | string | Name, under which the file is downloaded. |
| `disk` | string(64) | Disk the file is stored on. Hidden from JSON. |
| `path` | string(1024) | Path on the disk. Hidden from JSON. |
| `mime_type` | string | Type recognized from the contents. |
| `size` | unsigned big integer | Size in bytes. |
| `sha256` | char(64) | SHA-256 hash of the contents. |
| `metadata` | JSON, nullable | Whatever the app attached the file with. |
| `created_at`, `updated_at` | timestamps | |

An index on `fileable_type`, `fileable_id` and `collection` serves the lists of a model's files.

## The `File` model

Only `name` and `metadata` are fillable; the rest is set when the file is attached. Setting `name` cleans it (see
[Attaching files](01-attaching-files.md#what-gets-recorded)).

| Member | Description |
|---|---|
| `fileable()` | `MorphTo` relation to the model. |
| `storage()` | The `FilesystemAdapter` of the file's disk. |
| `contents()` | Contents of the stored file, or `null` when it is missing. |
| `isStored()` | Whether the stored file exists on its disk. |
| `download()` | A `StreamedResponse`, which downloads the file under its name. |
| `temporaryUrl($expiresAt)` | A temporary URL of the disk, which downloads the file. |
| `url` | Attribute: a temporary URL, or `null` on a disk without them. |
| `contentDisposition()` | The `Content-Disposition` header of a download. |
| `File::cleanName($name)` | Cleans a name as the `name` attribute does. |

Deleting a `File` deletes the stored file, once the deletion is committed. Downloads and URLs are described in
[Downloads and URLs](04-downloads-and-urls.md).

## Storage paths

A file is stored on the files disk at `{directory}/{morph}/{id}/{uuid}.{extension}`:

- `{directory}` is `api-core.files.directory` (`files`) without its outer slashes; an empty one is left out.
- `{morph}` is the fileable's alias in the morph map, or else a slug of its class, such as `models.invoices`.
- `{id}` is the fileable's key, and the UUID makes each path unique, so files of the same name never collide.
- `{extension}` is the one of the type recognized from the contents, not the one of the name. A type without a known
  extension gets none.

So a PDF attached to invoice 42 lands at `files/models.invoices/42/0d7a7f0e-5c1b-4b6a-9a52-3f4e8b1c2d90.pdf`. A model
can keep its files elsewhere by overriding `filesDisk()` or `filesDirectory()`.

## Configuration

| Key | Env | Default | Description |
|---|---|---|---|
| `api-core.files.disk` | `API_CORE_FILES_DISK` | `local` | Disk the files are stored on. It should be private. |
| `api-core.files.directory` | `API_CORE_FILES_DIRECTORY` | `files` | Directory, under which each model's files are stored. |
| `api-core.files.max_size` | `API_CORE_FILES_MAX_SIZE` | `10240` | The maximum size of an upload, in kilobytes. |
| `api-core.files.extensions` | | `pdf`, `txt`, `csv`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `doc`, `docx`, `xls`, `xlsx`, `odt`, `ods` | Extensions an upload may have. The app's list replaces this one as a whole. |
| `api-core.files.url_lifetime` | `API_CORE_FILES_URL_LIFETIME` | `5` | Lifetime of a file's temporary URL, in minutes. |

## In tests

`File::factory()` makes a file of a new user by default. `for($model, 'fileable')` attaches it to another model,
`stored($contents, $mimeType)` puts the contents on the disk (without it, only the row exists and `isStored()` is
false), and `inCollection($collection)` sets the collection:

```php
Storage::fake(config('api-core.files.disk'));

$file = File::factory()
    ->for($invoice, 'fileable')
    ->stored('Contents of the scan.')
    ->create(['name' => 'scan.txt']);
```

## Related

- [Attaching files](01-attaching-files.md)
- [File routes](02-file-routes.md)
- [Upload validation](03-upload-validation.md)
- [Downloads and URLs](04-downloads-and-urls.md)
- [Nesting under a parent](../http/controllers/05-nesting-under-a-parent.md)
- [Base model and the morph map](../models/00-base-model.md)
- [Migrations](../database/00-migrations.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
- [Configuration](../getting-started/03-configuration.md)
