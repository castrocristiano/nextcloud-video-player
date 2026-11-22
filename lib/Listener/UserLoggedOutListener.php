<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Listener;

use OCA\FolderVideoPlayer\Service\PlaybackSessionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserLoggedOutEvent;

/**
 * @implements IEventListener<UserLoggedOutEvent>
 */
class UserLoggedOutListener implements IEventListener
{
    public function __construct(
        private PlaybackSessionService $sessionService
    ) {
    }

    public function handle(Event $event): void
    {
        if (!$event instanceof UserLoggedOutEvent) {
            return;
        }
        $user = $event->getUser();
        if ($user === null) {
            return;
        }
        $uid = trim($user->getUID());
        if ($uid !== '') {
            $this->sessionService->revokeAllForUser($uid);
        }
    }
}
