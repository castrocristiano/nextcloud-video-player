<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\BackgroundJob;

use OCA\FolderVideoPlayer\BackgroundJob\SessionCleanupJob;
use OCA\FolderVideoPlayer\Listener\UserLoggedOutListener;
use OCA\FolderVideoPlayer\Service\PlaybackSessionService;
use OCP\IUser;
use OCP\User\Events\UserLoggedOutEvent;
use PHPUnit\Framework\TestCase;

class SessionCleanupAndLogoutTest extends TestCase
{
    public function testSessionCleanupJobRunsAtConfiguredInterval(): void
    {
        $sessionService = $this->createMock(PlaybackSessionService::class);
        $sessionService->expects($this->once())
            ->method('cleanupExpiredSessions')
            ->willReturn(['expiredCount' => 2, 'purgedCount' => 1]);

        $job = new SessionCleanupJob($sessionService);
        $this->assertSame(900, $job->getInterval());
        $job->run([]);
    }

    public function testUserLoggedOutListenerRevokesActiveSessionsForUser(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');

        $sessionService = $this->createMock(PlaybackSessionService::class);
        $sessionService->expects($this->once())
            ->method('revokeAllForUser')
            ->with('alice')
            ->willReturn(2);

        $listener = new UserLoggedOutListener($sessionService);
        $listener->handle(new UserLoggedOutEvent($user));
    }
}
