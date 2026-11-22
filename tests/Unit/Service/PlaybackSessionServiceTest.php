<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use OCA\FolderVideoPlayer\Db\PlaybackSessionEntity;
use OCA\FolderVideoPlayer\Db\PlaybackSessionMapper;
use OCA\FolderVideoPlayer\Service\ConcurrentSessionLimitException;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCA\FolderVideoPlayer\Service\PlaybackSessionService;
use OCA\FolderVideoPlayer\Service\ProgressService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class PlaybackSessionServiceTest extends TestCase
{
    public function testSessionLifecycleHeartbeatIsolationAndConcurrentLimit(): void
    {
        /** @var array<string, PlaybackSessionEntity> $sessionsByToken */
        $sessionsByToken = [];

        $mapper = $this->createMock(PlaybackSessionMapper::class);
        $mapper->method('insert')
            ->willReturnCallback(static function (PlaybackSessionEntity $e) use (&$sessionsByToken): PlaybackSessionEntity {
                $sessionsByToken[$e->getSessionToken()] = $e;
                return $e;
            });
        $mapper->method('update')
            ->willReturnCallback(static function (PlaybackSessionEntity $e) use (&$sessionsByToken): PlaybackSessionEntity {
                $sessionsByToken[$e->getSessionToken()] = $e;
                return $e;
            });
        $mapper->method('findByToken')
            ->willReturnCallback(static function (string $t) use (&$sessionsByToken): ?PlaybackSessionEntity {
                return $sessionsByToken[$t] ?? null;
            });
        $mapper->method('findActiveForUser')
            ->willReturnCallback(static function (string $uid, int $now) use (&$sessionsByToken): array {
                $active = [];
                foreach ($sessionsByToken as $s) {
                    if ($s->getUserId() === $uid && in_array($s->getStatus(), ['active', 'paused'], true) && $s->getExpiresAt() >= $now) {
                        $active[] = $s;
                    }
                }
                return $active;
            });

        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')
            ->willReturn([['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']]);

        $file = $this->createMock(File::class);
        $folder = $this->createMock(Folder::class);
        $resolver = $this->createMock(SafeNodeResolver::class);
        $resolver->method('resolveFileInRoot')
            ->willReturn(['root' => ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'], 'rootFolder' => $folder, 'file' => $file, 'relativePath' => '/Film.mp4']);
        $resolver->method('normalizeRelativePath')->willReturn('Film.mp4');

        $progressService = $this->createMock(ProgressService::class);
        $progressService->method('saveProgress')->willReturn([
            'rootId' => 'rk1',
            'relativePath' => '/Film.mp4',
            'currentTime' => 120.0,
            'duration' => 600.0,
            'progressRatio' => 0.2,
            'completed' => false,
            'updatedAt' => time(),
        ]);

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, string $def = ''): string => match ($key) {
            'session_ttl_seconds' => '180',
            'max_concurrent_sessions' => '1',
            default => $def,
        });

        $service = new PlaybackSessionService($mapper, $folderService, $resolver, $progressService, $config);

        // 1. Start session
        $started = $service->startSession('alice', 'rk1', 'Film.mp4', 'direct', 'Firefox');
        $token = $started['sessionToken'];
        $this->assertNotEmpty($token);
        $this->assertSame('active', $started['status']);

        // 2. Validate streaming session
        $validated = $service->validateStreamingSession('alice', $token, 'rk1', 'Film.mp4');
        $this->assertSame($token, $validated->getSessionToken());

        // 3. Another user cannot access or heartbeat Alice's session
        $wrongUserRejected = false;
        try {
            $service->heartbeat('bob', $token, 50.0, 600.0);
        } catch (NotPermittedException) {
            $wrongUserRejected = true;
        }
        $this->assertSame(true, $wrongUserRejected);

        // 4. Exceeding max_concurrent_sessions = 1 throws ConcurrentSessionLimitException
        try {
            $service->startSession('alice', 'rk1', 'Film.mp4');
            $this->fail('Expected ConcurrentSessionLimitException');
        } catch (ConcurrentSessionLimitException $e) {
            $this->assertSame(1, $e->getMaxConcurrentSessions());
            $this->assertCount(1, $e->getActiveSessions());
        }

        // 5. Heartbeat updates progress and status
        $hb = $service->heartbeat('alice', $token, 120.0, 600.0, 'paused');
        $this->assertSame('paused', $hb['status']);
        $this->assertNotNull($hb['progress']);

        // 6. Close session frees concurrency slot
        $closed = $service->closeSession('alice', $token, 150.0, 600.0);
        $this->assertSame('ended', $closed['status']);

        // 7. Using closed session for streaming fails
        $this->expectException(NotPermittedException::class);
        $service->validateStreamingSession('alice', $token, 'rk1', 'Film.mp4');
    }

    public function testStartSessionReusesLatestSessionAndCleansUpOlderSessions(): void
    {
        $existing = new PlaybackSessionEntity();
        $existing->setId(10);
        $existing->setUserId('alice');
        $existing->setSessionToken('old-token-123');
        $existing->setStatus('ended');

        $insertCalled = false;
        $updatedEntity = null;
        $cleanedUser = null;
        $cleanedKeepId = null;

        $mapper = $this->createMock(PlaybackSessionMapper::class);
        $mapper->method('findActiveForUser')->willReturn([]);
        $mapper->method('findLatestForUser')->with('alice')->willReturn($existing);
        $mapper->method('insert')->willReturnCallback(static function (PlaybackSessionEntity $e) use (&$insertCalled): PlaybackSessionEntity {
            $insertCalled = true;
            return $e;
        });
        $mapper->method('update')->willReturnCallback(static function (PlaybackSessionEntity $e) use (&$updatedEntity): PlaybackSessionEntity {
            $updatedEntity = $e;
            return $e;
        });
        $mapper->method('deleteOtherSessionsForUser')->willReturnCallback(static function (string $uid, int $keepId) use (&$cleanedUser, &$cleanedKeepId): int {
            $cleanedUser = $uid;
            $cleanedKeepId = $keepId;
            return 5;
        });

        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')
            ->willReturn([['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']]);

        $file = $this->createMock(File::class);
        $folder = $this->createMock(Folder::class);
        $resolver = $this->createMock(SafeNodeResolver::class);
        $resolver->method('resolveFileInRoot')
            ->willReturn(['root' => ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'], 'rootFolder' => $folder, 'file' => $file, 'relativePath' => '/Film.mp4']);

        $progressService = $this->createMock(ProgressService::class);
        $progressService->method('getVideoProgress')->willReturn(null);

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, string $def = ''): string => match ($key) {
            'session_ttl_seconds' => '180',
            'max_concurrent_sessions' => '0',
            default => $def,
        });

        $service = new PlaybackSessionService($mapper, $folderService, $resolver, $progressService, $config);
        $started = $service->startSession('alice', 'rk1', 'Film.mp4', 'transcode', 'Firefox');

        $this->assertFalse($insertCalled);
        $this->assertNotNull($updatedEntity);
        $this->assertSame(10, $updatedEntity->getId());
        $this->assertSame('active', $updatedEntity->getStatus());
        $this->assertNotSame('old-token-123', $started['sessionToken']);
        $this->assertSame('alice', $cleanedUser);
        $this->assertSame(10, $cleanedKeepId);
    }
}
