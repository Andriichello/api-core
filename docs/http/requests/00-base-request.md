# Base request

`Core\Http\Requests\BaseRequest` is the form request all of api-core's requests extend. It adds default and overriding
input values that apply before validation, the organization a request acts in, and a `createFrom()` that CRUD
controllers make their operations' requests with. Extend it for a request that isn't a CRUD operation's.

## Rules

`rules()` returns no rules by default. A request overrides it as any Laravel form request does:

```php
use Core\Http\Requests\BaseRequest;

class PreviewInvoiceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'lines' => ['required', 'array'],
            'lines.*.amount' => ['required', 'numeric'],
        ];
    }
}
```

For the requests of CRUD operations, extend `StoreRequest` and `UpdateRequest` instead
(see [CRUD requests](01-crud-requests.md)).

## Defaults and overrides

Two methods change the input before it's validated:

- `defaults()` returns values for the fields the input is missing (merged with `mergeIfMissing()`).
- `overrides()` returns values that replace the input's (merged with `merge()`), after the defaults.

```php
class StoreInvoiceRequest extends StoreRequest
{
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    protected function defaults(): array
    {
        return ['currency' => 'EUR'];
    }

    protected function overrides(): array
    {
        $currency = $this->input('currency');

        return is_string($currency) ? ['currency' => strtoupper($currency)] : [];
    }
}
```

They're merged when the validator is made, so the rules validate them, `input()` and `all()` return them, and
`validated()` includes them when they have rules. A value without a rule isn't in `validated()`, so a CRUD controller
doesn't save it. `InviteUserRequest` lowercases the email in `overrides()`, so it's compared as it's stored.

## Why not `prepareForValidation()`

A CRUD controller makes each operation's request with `createFrom()`, which builds the validator right away, with a
copy of the input, before the parents are resolved and the policy is asked. Laravel calls `prepareForValidation()`
later, in `validateResolved()`, so what it changes reaches `input()` but neither the rules nor `validated()`.
`defaults()` and `overrides()` work both when a CRUD controller makes the request and when Laravel injects it into a
controller method, so normalize input with them.

For the same reason, `rules()` of a CRUD operation's request runs when the request is made: before the policy check,
and before its parents are set, so `getParents()` is still empty there.

## The organization of a request

`orgId()` returns the id of the organization the request acts in: the current one, which the `organization`
middleware sets, or the signed-in user's own when the route doesn't have the middleware. It never comes from the
input, so a client can't name another organization in the body. Use it in rules that are unique per organization:

```php
'number' => [
    'required',
    'string',
    Rule::unique('invoices', 'number')->where('organization_id', $this->orgId()),
],
```

## Authorization

A CRUD operation's request is authorized by the controller's policy. A request may also define Laravel's
`authorize()`. When it returns `false`, the request fails with 403 and the message of the `$message` property, which
is `You are not authorized to perform this request.` unless a subclass changes it:

```php
protected string $message = 'Only drafts can be previewed.';

public function authorize(): bool
{
    return $this->input('status', 'draft') === 'draft';
}
```

## Making a request from another

`createFrom(Request $from, $to = null, ?array $data = null)` makes the request from another one, keeping its route,
user and input, applies `defaults()` and `overrides()`, and builds the validator. With `$data`, the input is replaced
by it, as a JSON body. `validateResolved()` then authorizes and validates it, and `validated()` returns the result:

```php
$preview = PreviewInvoiceRequest::createFrom($request, null, ['client_id' => 2, 'lines' => []]);
$preview->validateResolved(); // throws a ValidationException, which answers 422

$preview->validated();
```

## Other helpers

- `clear()` removes the input parameters of the request.
- `userAgent($default = 'NULL')` returns the `User-Agent` header transliterated to ASCII, or `'NULL'` when it's missing.
  api-core names the access tokens it creates with it.

## Related

- [CRUD requests](01-crud-requests.md)
- [Targets](02-targets.md)
- [Request traits](03-request-traits.md)
- [CRUD controller](../controllers/01-crud-controller.md)
- [Current organization](../../organizations/01-current-organization.md)
- [Resolving the organization](../../organizations/02-resolving-the-organization.md)
- [Errors](../02-errors.md)
