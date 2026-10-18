<?php

namespace Core\Notifications;

use Carbon\CarbonInterval;
use Core\Http\Requests\Auth\AcceptInvitationRequest;
use Core\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Uri;

/**
 * Class InvitationNotification.
 *
 * Invites a person to an account, which was made for them: its email links to
 * the SPA's page (`api-core.auth.invitation_url`), where they accept the
 * invitation by choosing their password.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Notifications
 */
class InvitationNotification extends Notification
{
    /**
     * InvitationNotification constructor.
     *
     * @param User|null $inviter The user, who invited the person, if any.
     */
    public function __construct(
        public readonly ?User $inviter = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param User $notifiable
     *
     * @return array<int, string>
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param User $notifiable
     *
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $place = $notifiable->organization->name ?? config('app.name');
        $lifetime = CarbonInterval::minutes((int) config('api-core.auth.invitation_lifetime'))->cascade()->forHumans();

        return (new MailMessage)
            ->subject(__('You are invited to :place', ['place' => $place]))
            ->line($this->inviter
                ? __(':inviter invited you to join :place.', ['inviter' => $this->inviter->name, 'place' => $place])
                : __('You are invited to join :place.', ['place' => $place]))
            ->action(__('Accept invitation'), $this->url($notifiable))
            ->line(__('The link expires in :lifetime.', ['lifetime' => $lifetime]));
    }

    /**
     * Get the URL of the SPA's page, which accepts the invitation to the
     * account.
     *
     * @param User $notifiable
     *
     * @return string
     */
    public function url(User $notifiable): string
    {
        return Uri::of(config('api-core.auth.invitation_url'))
            ->withQuery(AcceptInvitationRequest::linkParameters($notifiable))
            ->value();
    }
}
