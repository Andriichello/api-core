# Attaching files

The `HasFiles` trait lets a model keep files: it stores uploads and generated contents on the files disk and records
them as `File`s of the model. Use it, with `HasFilesInterface`, on every model that has files.

## Setup

```php
namespace App\Models;

use Core\Models\BaseModel;
use Core\Models\Interfaces\HasFilesInterface;
use Core\Models\Traits\BelongsToOrganization;
use Core\Models\Traits\HasFiles;

class Invoice extends BaseModel implements HasFilesInterface
{
    use BelongsToOrganization;
    use HasFiles;
}
```

The trait adds `files()` (a `MorphMany` of `File`), `filesIn($collection)`, `attachFile()`, `attachContents()`,
`filesDisk()` and `filesDirectory()`. The model must be saved before files are attached to it.

## Attaching an upload

`attachFile(SymfonyFile $file, ?string $name = null, string $collection = 'default', array $metadata = []): File`
stores a file and records it. An `UploadedFile` is attached under its client name, any other
`Symfony\Component\HttpFoundation\File\File` under its file name, unless a name is given:

```php
$file = $invoice->attachFile($request->file('scan'));

$file = $invoice->attachFile($request->file('scan'), 'Signed act.pdf', 'signed', ['signed_by' => $user->id]);
```

## Attaching generated contents

`attachContents(string $contents, string $name, string $collection = 'default', array $metadata = []): File`
stores contents, which the app made, such as a rendered PDF:

```php
$file = $invoice->attachContents($pdf, "Invoice {$invoice->number}.pdf", 'generated');
```

## What gets recorded

- `mime_type`, `size` and `sha256` come from the contents, never from the client. An upload's type is the one Symfony
  guesses from its contents (`getMimeType()`); generated contents are recognized with `finfo`. A type, which can't be
  recognized, is `application/octet-stream`.
- The stored path ends in the extension of that type, whatever the name says: a PDF uploaded as `invoice` is stored as
  a `.pdf` and keeps the name `invoice`.
- The name is cleaned: slashes and backslashes become dashes, control characters are removed, the name is trimmed and
  cut to 255 characters, and an empty one becomes `file`. `Act of 2026/10.pdf` is kept as `Act of 2026-10.pdf`.
- `metadata` is stored as JSON, or as `null` when it is empty.
- The returned `File` has its `fileable` relation set to the model.

## Collections

A collection groups a model's files, such as the uploaded ones (`default`) and the generated ones. `files()` gives all
of them and `filesIn()` those of one collection:

```php
$latest = $invoice->filesIn('generated')->latest('id')->first();
```

A `FileController` with a `$collection` lists, finds and adds only the files of that collection (see
[File routes](02-file-routes.md#collections)).

## Errors

| Exception | When |
|---|---|
| `LogicException` | The model isn't saved yet. Nothing is stored. |
| `RuntimeException` | The upload can't be read, the disk doesn't store the contents, or the record isn't saved, e.g. because a `creating` listener of `File` returned `false`. |

When recording fails for any reason, the stored file is deleted before the exception is rethrown, so no stored file
is left without a record.

## Transactions

A disk isn't transactional, so `HasFiles` ties a stored file to the database transaction, in which it is attached:
when that transaction rolls back, or any transaction around it does, the stored file is deleted.

```php
DB::transaction(function () use ($invoice, $pdf) {
    $invoice->update(['status' => 'sent']);
    $invoice->attachContents($pdf, 'invoice.pdf', 'generated');

    // An exception from here on rolls back the update and the record, and deletes the stored PDF
});
```

The deletion is registered on every open transaction of the connection, because Laravel drops the rollback callbacks
of a nested transaction, which committed, when the transaction around it rolls back. A nested transaction, which rolls
back while the outer one commits, deletes only the files attached within it.

Outside a transaction, the file is stored and recorded at once, and nothing undoes that, when a later step of the same
operation fails. Attach files in a transaction, when they belong to a larger change.

## Deleting files

- Deleting a `File` deletes the stored file once the deletion is committed. When the deletion rolls back, the stored
  file stays.
- Deleting the model deletes its files, each with its stored file.
- A soft-deleted model keeps its files until it is deleted permanently (`forceDelete()`), so restoring it brings them
  back.
- Deletes through a query, such as `Invoice::query()->delete()`, fire no model events, so they leave the files behind.

## Changing the disk or directory

`filesDisk()` returns `api-core.files.disk`, and `filesDirectory()` returns `{directory}/{morph}/{id}`, such as
`files/models.invoices/42`. Override them on a model to keep its files elsewhere:

```php
public function filesDisk(): string
{
    return 's3';
}
```

The disk is recorded with each file, so the files stored before a change stay readable.

## Related

- [Files](00-overview.md)
- [File routes](02-file-routes.md)
- [Upload validation](03-upload-validation.md)
- [Downloads and URLs](04-downloads-and-urls.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Base model and the morph map](../models/00-base-model.md)
