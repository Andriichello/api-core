# Notifications

`NotificationController` gives the signed-in user routes to their own database notifications: listing them, counting
the unread ones, marking them as read and deleting them. Use it when the app notifies users through Laravel's
`database` channel and a client shows those notifications.

## Setup

api-core's migrations create the `notifications` table (no `make:notifications-table` needed), and `Core\Models\User`
is `Notifiable`. Register the routes in `routes/api.php`; they bring their own `auth:sanctum` middleware:

```php
use Core\Http\Controllers\NotificationController;

NotificationController::routes();
```

A notification reaches these routes, when it goes through the `database` channel. Its `databaseType()` sets the `type`
(the class name without it), and `toArray()` (or `toDatabase()`) the `data`:

```php
namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification
{
    public function __construct(
        public readonly Invoice $invoice
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'invoice-overdue';
    }

    public function toArray(object $notifiable): array
    {
        return ['invoice_id' => $this->invoice->id, 'number' => $this->invoice->number];
    }
}
```

```php
$user->notify(new InvoiceOverdue($invoice));
```

## Routes

| Method | Path | Name | Description |
|---|---|---|---|
| `GET` | `/api/notifications` | `notifications.index` | A page of the user's notifications, newest first; only the unread ones with `?unread=1`. |
| `GET` | `/api/notifications/unread-count` | `notifications.unread-count` | The number of unread notifications. |
| `POST` | `/api/notifications/read` | `notifications.read-all` | Mark all unread notifications as read. |
| `POST` | `/api/notifications/{id}/read` | `notifications.read` | Mark one notification as read. |
| `DELETE` | `/api/notifications/{id}` | `notifications.destroy` | Delete one notification. |

Every route goes through the user's own `notifications()` relation, so a notification of another user is a 404, as if
it didn't exist. Notifications belong to an account, not to an organization: each of a person's linked accounts has
its own, and a switched account sees those of the account switched to.

## Listing

```http
GET /api/notifications?unread=1&page[size]=20
Authorization: Bearer {access-token}
```

```json
{
    "data": [
        {
            "id": "9f3c6a52-1d7e-4b8e-9a51-2f0c3e7d8b14",
            "type": "invoice-overdue",
            "data": {"invoice_id": 42, "number": "INV-2026-0042"},
            "read_at": null,
            "created_at": "2026-10-20T08:30:00.000000Z"
        }
    ],
    "meta": {
        "from": 1,
        "to": 1,
        "path": "https://api.example.com/api/notifications",
        "per_page": 20,
        "current_page": 1,
        "last_page": 1,
        "total": 1,
        "links": {
            "first": "https://api.example.com/api/notifications?unread=1&page%5Bsize%5D=20&page%5Bnumber%5D=1",
            "prev": null,
            "self": "https://api.example.com/api/notifications?unread=1&page%5Bsize%5D=20&page%5Bnumber%5D=1",
            "next": null,
            "last": "https://api.example.com/api/notifications?unread=1&page%5Bsize%5D=20&page%5Bnumber%5D=1"
        }
    },
    "message": "OK"
}
```

The order is newest first, by `created_at` and then by `id`, both descending, so notifications of the same second keep
one order and pages neither repeat nor skip any. `User::notifications()` itself has this order, so the app's code gets
it too. The list takes `page[number]` and `page[size]`, but no filters or sorts.

## Counting and marking

`GET /api/notifications/unread-count` answers `{"data": {"unread": 3}, "message": "OK"}`.

`POST /api/notifications/{id}/read` marks the notification as read and answers it, as in the list, with its `read_at`.
Marking a read notification again keeps it read.

`POST /api/notifications/read` marks all the user's unread notifications as read and answers how many it marked:
`{"data": {"read": 3}, "message": "OK"}`.

## Deleting

`DELETE /api/notifications/{id}` deletes the notification and answers `{"message": "Deleted"}`.

## Errors

| Status | When |
|---|---|
| 401 | The request isn't authenticated. |
| 404 | No notification of the user has the id. |

## Related

- [Paginating lists](../pagination/00-paginating-lists.md)
- [Paginated responses](../pagination/01-paginated-responses.md)
- [Responses](../http/01-responses.md)
- [Linked accounts](../auth/06-linked-accounts.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Migrations](../database/00-migrations.md)
