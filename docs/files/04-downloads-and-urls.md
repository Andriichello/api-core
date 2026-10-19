# Downloads and URLs

A file is downloaded through the API, which checks access to its model on every request, or through a temporary URL of
its disk, which works without the API until it expires. This page covers both, and the names files are downloaded
under.

## The download route

`GET {uri}/{id}/download` of a `FileController` streams the stored file, after the same checks as showing it (the
`view` ability on the fileable, see [File routes](02-file-routes.md#access)):

```http
GET /api/invoices/42/files/7/download
Authorization: Bearer {access-token}
```

```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Length: 48213
Content-Disposition: attachment; filename="Act of October.pdf"
X-Content-Type-Options: nosniff
```

- `Content-Type` is the type recognized from the contents when the file was attached, never the client's.
- `Content-Disposition` is always `attachment`, so a browser saves the file rather than opening it in the app's origin.
- `X-Content-Type-Options: nosniff` stops browsers from guessing another type from the contents.

When the record exists, but its stored file is missing from the disk, the route answers a JSON 404:

```json
{
    "message": "The stored file is missing."
}
```

In the app's own code, `File::download()` gives the same response, and `contents()` the contents (or `null`, when the
stored file is missing):

```php
return $invoice->filesIn('generated')->latest('id')->firstOrFail()->download();
```

## File names

A file is downloaded under its `name`. The `Content-Disposition` header has the name in UTF-8 (`filename*`) for clients,
which read it, and an ASCII fallback (`filename`) for those, which don't. The fallback is the name transliterated with
`Str::ascii()`, without `%` signs. When nothing of the name can be transliterated, such as a name of emoji, the
fallback is `file` with the name's extension.

| Name | `Content-Disposition` |
|---|---|
| `act.pdf` | `attachment; filename=act.pdf` |
| `Рахунок 1.txt` | `attachment; filename="Raxunok 1.txt"; filename*=utf-8''%D0%A0%D0%B0%D1%85%D1%83%D0%BD%D0%BE%D0%BA%201.txt` |
| `😀😀.txt` | `attachment; filename=file.txt; filename*=utf-8''%F0%9F%98%80%F0%9F%98%80.txt` |

`File::contentDisposition()` builds this header for downloads and temporary URLs alike.

## Temporary URLs

The `url` attribute of a file is a temporary URL, which downloads it, or `null`, when its disk doesn't provide temporary
URLs. It isn't in a file's JSON by default; a client asks for it with `?append=url`:

```http
GET /api/invoices/42/files/7?append=url
Authorization: Bearer {access-token}
```

```json
{
    "data": {
        "id": 7,
        "fileable_type": "models.invoices",
        "fileable_id": 42,
        "collection": "default",
        "name": "Act of October.pdf",
        "mime_type": "application/pdf",
        "size": 48213,
        "sha256": "50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c",
        "metadata": null,
        "created_at": "2026-10-20T09:15:02.000000Z",
        "updated_at": "2026-10-20T09:15:02.000000Z",
        "url": "https://example-bucket.s3.eu-central-1.amazonaws.com/files/models.invoices/42/0d7a7f0e-5c1b-4b6a-9a52-3f4e8b1c2d90.pdf?X-Amz-Expires=300&X-Amz-Signature=…"
    },
    "message": "OK"
}
```

The URL expires after `api-core.files.url_lifetime` minutes (`API_CORE_FILES_URL_LIFETIME`, 5 by default). In code,
`temporaryUrl($expiresAt)` gives one with another expiry:

```php
$url = $file->temporaryUrl(now()->addHour());
```

Which disks give URLs:

- `s3` gives a pre-signed URL. It passes the file's type and `Content-Disposition`, so the download has the file's name.
- `local` gives one, when its `serve` option is on (as it is in Laravel's default `config/filesystems.php`): a signed
  URL of Laravel's route, which serves the disk. That route ignores the type and the disposition, so the file is
  downloaded under its stored name (the UUID), not its `name`.
- Other disks give none, and `url` is `null`. Calling `temporaryUrl()` on them throws a `RuntimeException`.

Anyone, who holds a temporary URL, can download the file until it expires, without any check of access. Keep the
lifetime short, and prefer the download route, when the user's access must be checked at the moment of the download.

## Related

- [Files](00-overview.md)
- [File routes](02-file-routes.md)
- [Attaching files](01-attaching-files.md)
- [Appends](../http/resources/03-appends.md)
- [Errors](../http/02-errors.md)
- [Configuration](../getting-started/03-configuration.md)
