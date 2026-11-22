<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Controller;

use OCA\NextcloudVideoPlayer\Controller\PreferenceController;
use OCA\NextcloudVideoPlayer\Controller\SessionController;
use OCA\NextcloudVideoPlayer\Service\ConcurrentSessionLimitException;
use OCA\NextcloudVideoPlayer\Service\PlaybackSessionService;
use OCA\NextcloudVideoPlayer\Service\PreferenceService;
use OCP\AppFramework\Http\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SessionAndPreferenceControllerTest extends TestCase
{
    public function testSessionControllerStartHeartbeatListCloseAndLimit429(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'rootId' => 'rk1',
            'path' => '/Film.mp4',
            'streamMode' => 'direct',
            'currentTime' => 45.0,
            'duration' => 300.0,
            'status' => 'active',
            'completed' => false,
            default => $d,
        });
        $request->method('getHeader')->with('User-Agent')->willReturn('TestBrowser/1.0');

        $callCount = 0;
        $sessionService = $this->createMock(PlaybackSessionService::class);
        $sessionService->method('startSession')
            ->willReturnCallback(static function () use (&$callCount): array {
                $callCount++;
                if ($callCount > 1) {
                    throw new ConcurrentSessionLimitException('Limit reached', 1, [['sessionToken' => 'tok1']]);
                }
                return [
                    'sessionToken' => 'tok1',
                    'rootId' => 'rk1',
                    'relativePath' => '/Film.mp4',
                    'streamMode' => 'direct',
                    'status' => 'active',
                    'startedAt' => 1700000000,
                    'expiresAt' => 1700000180,
                    'ttlSeconds' => 180,
                    'progress' => null,
                ];
            });
        $sessionService->method('listActiveSessions')->willReturn([['sessionToken' => 'tok1']]);
        $sessionService->method('heartbeat')->willReturn(['sessionToken' => 'tok1', 'status' => 'active', 'expiresAt' => 1700000200, 'progress' => null]);
        $sessionService->method('closeSession')->willReturn(['sessionToken' => 'tok1', 'status' => 'ended', 'endedAt' => 1700000210]);

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new SessionController('nextcloud_video_player', $request, $sessionService, $userSession, $l10n);

        $start1 = $controller->start();
        $this->assertSame(Http::STATUS_CREATED, $start1->getStatus());

        $start2 = $controller->start();
        $this->assertSame(Http::STATUS_TOO_MANY_REQUESTS, $start2->getStatus());

        $list = $controller->listActive();
        $this->assertSame(Http::STATUS_OK, $list->getStatus());
        $this->assertCount(1, $list->getData()['sessions']);

        $hb = $controller->heartbeat('tok1');
        $this->assertSame(Http::STATUS_OK, $hb->getStatus());

        $close = $controller->close('tok1');
        $this->assertSame(Http::STATUS_OK, $close->getStatus());

        $beacon = $controller->closeBeacon('tok1');
        $this->assertSame(Http::STATUS_OK, $beacon->getStatus());
    }

    public function testPreferenceControllerGetAndUpdate(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParams')->willReturn(['playbackRate' => 1.25]);

        $prefService = $this->createMock(PreferenceService::class);
        $prefService->method('getPreferences')->willReturn([
            'playbackRate' => 1.0,
            'autoPlayNext' => true,
            'sortBy' => 'name_asc',
            'volume' => 1.0,
            'muted' => false,
            'preferredSubtitle' => null,
            'updatedAt' => 0,
        ]);
        $prefService->method('updatePreferences')->willReturn([
            'playbackRate' => 1.25,
            'autoPlayNext' => true,
            'sortBy' => 'name_asc',
            'volume' => 1.0,
            'muted' => false,
            'preferredSubtitle' => null,
            'updatedAt' => 1700000000,
        ]);

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new PreferenceController('nextcloud_video_player', $request, $prefService, $userSession, $l10n);
        $this->assertSame(Http::STATUS_OK, $controller->get()->getStatus());
        $upd = $controller->update();
        $this->assertSame(Http::STATUS_OK, $upd->getStatus());
        $this->assertSame(1.25, $upd->getData()['preferences']['playbackRate']);
    }
}
