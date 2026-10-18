<?php

namespace Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Class NotificationResource.
 *
 * A notification of the signed-in user, which the `database` channel stored:
 * its type, data, and when it was read and sent.
 *
 * @property DatabaseNotification $resource
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class NotificationResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return [
            'id' => $notification->getKey(),
            'type' => $notification->getAttribute('type'),
            'data' => $notification->getAttribute('data'),
            'read_at' => $notification->getAttribute('read_at'),
            'created_at' => $notification->getAttribute('created_at'),
        ];
    }
}
