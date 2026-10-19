# Upload validation

Uploads through a `FileController` are validated by `StoreFileRequest`, which limits their size and type, and renames by
`UpdateFileRequest`. This page explains the limits, how to change them and the errors they give.

## Uploading

| Field | Rules |
|---|---|
| `file` | Required. A file of at most `api-core.files.max_size` kilobytes, whose extension and whose contents are both of a type in `api-core.files.extensions`. |
| `name` | Optional string of at most 255 characters. The file is attached under it instead of its client name, after the name is cleaned. |

The type is checked twice, with Laravel's `File::types($extensions)->extensions($extensions)` rule:

- the extension of the client's file name (`extensions`) must be in the list, and
- the extension of the type recognized from the contents (`types`, which checks like `mimes`) must be in the list too.

So a renamed file doesn't get through: an HTML page uploaded as `page.pdf` has an allowed name, but its contents are
recognized as HTML, which isn't allowed. A file, whose contents are allowed, but whose name has another extension,
such as a PDF uploaded as `act.exe`, is refused too.

Nothing is stored when validation fails. A valid upload looks like this:

```http
POST /api/invoices/42/files
Authorization: Bearer {access-token}
Content-Type: multipart/form-data; boundary=boundary

--boundary
Content-Disposition: form-data; name="file"; filename="act.pdf"
Content-Type: application/pdf

%PDF-1.4 …
--boundary
Content-Disposition: form-data; name="name"

Act of October.pdf
--boundary--
```

The file is attached as `Act of October.pdf`; without `name`, it would be attached as `act.pdf`. The response is in
[File routes](02-file-routes.md#responses).

## Changing the limits

`max_size` is in kilobytes (`10240` is 10 MB):

```env
API_CORE_FILES_MAX_SIZE=20480
```

PHP's `upload_max_filesize` and `post_max_size`, and the web server's limit of a request's body, must allow at least as
much, or larger uploads fail before this validation runs.

The extensions are set in the app's `config/api-core.php` (published with `--tag=api-core-config`). The app's list
replaces api-core's as a whole, so it names every allowed extension:

```php
'files' => [
    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'xml'],
],
```

The type recognized from the contents may be more general than the name suggests, such as plain text for some CSV
files, so keep the general type's extension (`txt`) in the list along with the specific one, and try the list with
real files of the app's users.

## Renaming

`PATCH {uri}/{id}` changes only the name, under which the file is downloaded; the stored file stays where it is.

| Field | Rules |
|---|---|
| `name` | Optional string of at most 255 characters, cleaned like an upload's name. |

Other fields of the body are ignored, but a body without `name`, such as `{}` or `{"collection": "signed"}`, is refused
with a 422 on `fields`, as every update without a field to update is
(see [CRUD requests](../http/requests/01-crud-requests.md)).

## Errors

A 422 names the field, with Laravel's messages in the request's locale. A disguised upload gives:

```json
{
    "message": "The file field must be a file of type: pdf, txt, csv, jpg, jpeg, png, gif, webp, doc, docx, xls, xlsx, odt, ods.",
    "errors": {
        "file": [
            "The file field must be a file of type: pdf, txt, csv, jpg, jpeg, png, gif, webp, doc, docx, xls, xlsx, odt, ods."
        ]
    }
}
```

| Case | Error on `file` |
|---|---|
| No file | `The file field is required.` |
| Not a file, e.g. a string | `The file field must be a file.`, along with the type errors. |
| Too large | `The file field must not be greater than 10240 kilobytes.` |
| Contents of a type not in the list | `The file field must be a file of type: …` |
| Name with an extension not in the list | `The file field must have one of the following extensions: …` |

A rename without `name` gives `The request has no fields to update.` or
`None of the given fields can be updated: collection.` on `fields`.

## In tests

`UploadedFile::fake()` reports a type taken from the file's name, not from its contents, so a test of a disguised file
needs a real upload, whose type is recognized from its contents:

```php
$path = tempnam(sys_get_temp_dir(), 'upload');
file_put_contents($path, '<html><body>Page</body></html>');

$this->postJson('/api/invoices/42/files', ['file' => new UploadedFile($path, 'page.pdf', null, null, true)])
    ->assertUnprocessable()
    ->assertJsonValidationErrors('file');
```

## Related

- [File routes](02-file-routes.md)
- [Attaching files](01-attaching-files.md)
- [Files](00-overview.md)
- [Errors](../http/02-errors.md)
- [CRUD requests](../http/requests/01-crud-requests.md)
- [Translations](../localization/01-translations.md)
- [Configuration](../getting-started/03-configuration.md)
