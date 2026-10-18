<?php

namespace Core\Http\Controllers;

use Core\Http\Resources\NotificationCollection;
use Core\Http\Resources\NotificationResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\User;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

/**
 * Class NotificationController.
 *
 * The signed-in user's notifications, which the `database` channel stored:
 * it lists them, newest first (only the unread ones with `?unread=1`),
 * counts the unread ones, marks one or all of them as read, and deletes
 * one. A user reaches only their own notifications.
 *
 * `routes()` registers its routes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 */
class NotificationController extends ResourceController
{
    /**
     * Resource class, which wraps a single model.
     *
     * @var class-string<NotificationResource>
     */
    protected string $resourceClass = NotificationResource::class;

    /**
     * Collection class, which wraps a list of models.
     *
     * @var class-string<NotificationCollection>
     */
    protected string $collectionClass = NotificationCollection::class;

    /**
     * Register the routes of the notifications, which need a signed-in user.
     *
     * @return void
     */
    public static function routes(): void
    {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('notifications', [static::class, 'index'])->name('notifications.index');
            Route::get('notifications/unread-count', [static::class, 'unreadCount'])
                ->name('notifications.unread-count');
            Route::post('notifications/read', [static::class, 'markAllAsRead'])->name('notifications.read-all');
            Route::post('notifications/{id}/read', [static::class, 'markAsRead'])->name('notifications.read');
            Route::delete('notifications/{id}', [static::class, 'destroy'])->name('notifications.destroy');
        });
    }

    /**
     * List a page of the user's notifications, newest first, or only of the
     * unread ones with `?unread=1`.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws AuthenticationException
     * @throws Exception
     */
    public function index(Request $request): ApiResponse
    {
        $query = $this->notificationsOf($request)->getQuery();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return $this->asPaginatedResponse($query, $request);
    }

    /**
     * Count the user's unread notifications.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws AuthenticationException
     */
    public function unreadCount(Request $request): ApiResponse
    {
        return ApiResponse::ok([
            'data' => ['unread' => $this->userOf($request)->unreadNotifications()->count()],
        ]);
    }

    /**
     * Mark the user's notification as read.
     *
     * @param Request $request
     * @param string $id
     *
     * @return ApiResponse
     * @throws AuthenticationException
     * @throws ModelNotFoundException
     */
    public function markAsRead(Request $request, string $id): ApiResponse
    {
        $notification = $this->notificationOf($request, $id);
        $notification->markAsRead();

        return ApiResponse::ok([
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark all the user's unread notifications as read.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws AuthenticationException
     */
    public function markAllAsRead(Request $request): ApiResponse
    {
        $count = $this->userOf($request)->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::ok([
            'data' => ['read' => $count],
        ]);
    }

    /**
     * Delete the user's notification.
     *
     * @param Request $request
     * @param string $id
     *
     * @return ApiResponse
     * @throws AuthenticationException
     * @throws ModelNotFoundException
     */
    public function destroy(Request $request, string $id): ApiResponse
    {
        $this->notificationOf($request, $id)->delete();

        return ApiResponse::ok()
            ->setMessage('Deleted');
    }

    /**
     * Find the user's notification by its id.
     *
     * @param Request $request
     * @param string $id
     *
     * @return DatabaseNotification
     * @throws AuthenticationException
     * @throws ModelNotFoundException
     */
    protected function notificationOf(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->notificationsOf($request)->findOrFail($id);

        return $notification;
    }

    /**
     * Get the user's notifications, newest first.
     *
     * @param Request $request
     *
     * @return MorphMany
     * @throws AuthenticationException
     */
    protected function notificationsOf(Request $request): MorphMany
    {
        return $this->userOf($request)->notifications();
    }

    /**
     * Get the signed-in user.
     *
     * @param Request $request
     *
     * @return User
     * @throws AuthenticationException
     */
    protected function userOf(Request $request): User
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
