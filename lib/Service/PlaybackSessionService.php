<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use InvalidArgumentException;
use OCA\FolderVideoPlayer\AppInfo\Application;
use OCA\FolderVideoPlayer\Db\PlaybackSessionEntity;
use OCA\FolderVideoPlayer\Db\PlaybackSessionMapper;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

class PlaybackSessionService
{
    public const DEFAULT_TTL_SECONDS = 180;
    public const RETENTION_SECONDS = 2592000; // 30 days

    public function __construct(
        private PlaybackSessionMapper $sessionMapper,
        private FolderConfigService $folderConfigService,
        private SafeNodeResolver $safeNodeResolver,
        private ProgressService $progressService,
        private IConfig $config
    ) {
    }

    /**
     * @return array{sessionToken: string, rootId: string, relativePath: string, streamMode: string, status: string, startedAt: int, expiresAt: int, ttlSeconds: int, progress: ?array<string, mixed>}
     */
    public function startSession(
        string $userId,
        string $rootKey,
        string $relativePath,
        string $streamMode = 'direct',
        ?string $clientAgent = null
    ): array {
        $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
        $resolvedFile = $this->safeNodeResolver->resolveFileInRoot($userId, $rootKey, $relativePath, $effectiveRoots);
        $canonicalRelative = $resolvedFile['relativePath'];

        $now = time();
        $this->sessionMapper->expireStaleSessions($now, $userId);

        $maxConcurrent = $this->getMaxConcurrentSessions();
        $activeSessions = $this->sessionMapper->findActiveForUser($userId, $now);

        if ($maxConcurrent > 0 && count($activeSessions) >= $maxConcurrent) {
            throw new ConcurrentSessionLimitException(
                'Maximum number of concurrent playback sessions reached',
                $maxConcurrent,
                array_map(fn (PlaybackSessionEntity $s): array => $this->entityToArray($s), $activeSessions)
            );
        }

        $ttl = $this->getSessionTtlSeconds();
        $token = bin2hex(random_bytes(32));
        $mode = $streamMode === 'transcode' ? 'transcode' : 'direct';
        $cleanAgent = $clientAgent !== null ? substr(trim($clientAgent), 0, 255) : null;

        $entity = new PlaybackSessionEntity();
        $entity->setSessionToken($token);
        $entity->setUserId($userId);
        $entity->setRootKey($rootKey);
        $entity->setRelativePath($canonicalRelative);
        $entity->setStreamMode($mode);
        $entity->setClientAgent($cleanAgent);
        $entity->setStatus('active');
        $entity->setStartedAt($now);
        $entity->setLastHeartbeatAt($now);
        $entity->setExpiresAt($now + $ttl);
        $entity->setEndedAt(null);

        $this->sessionMapper->insert($entity);

        return [
            'sessionToken' => $token,
            'rootId' => $rootKey,
            'relativePath' => $canonicalRelative,
            'streamMode' => $mode,
            'status' => 'active',
            'startedAt' => $now,
            'expiresAt' => $now + $ttl,
            'ttlSeconds' => $ttl,
            'progress' => $this->progressService->getVideoProgress($userId, $rootKey, $canonicalRelative),
        ];
    }

    /**
     * @return array{sessionToken: string, status: string, expiresAt: int, progress: ?array<string, mixed>}
     */
    public function heartbeat(
        string $userId,
        string $sessionToken,
        ?float $positionSeconds = null,
        ?float $durationSeconds = null,
        string $status = 'active',
        bool $completed = false
    ): array {
        $entity = $this->requireOwnedActiveSession($userId, $sessionToken);

        $now = time();
        $ttl = $this->getSessionTtlSeconds();
        $newStatus = $status === 'paused' ? 'paused' : 'active';

        $entity->setStatus($newStatus);
        $entity->setLastHeartbeatAt($now);
        $entity->setExpiresAt($now + $ttl);
        $this->sessionMapper->update($entity);

        $savedProgress = null;
        if ($positionSeconds !== null && $durationSeconds !== null && $durationSeconds > 0.0) {
            $savedProgress = $this->progressService->saveProgress(
                $userId,
                $entity->getRootKey(),
                $entity->getRelativePath(),
                $positionSeconds,
                $durationSeconds,
                $completed,
                null,
                $sessionToken
            );
        }

        return [
            'sessionToken' => $entity->getSessionToken(),
            'status' => $newStatus,
            'expiresAt' => $entity->getExpiresAt(),
            'progress' => $savedProgress,
        ];
    }

    /**
     * @return array{sessionToken: string, status: string, endedAt: int}
     */
    public function closeSession(
        string $userId,
        string $sessionToken,
        ?float $positionSeconds = null,
        ?float $durationSeconds = null,
        bool $completed = false
    ): array {
        $trimmed = trim($sessionToken);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Session token is required');
        }

        $entity = $this->sessionMapper->findByToken($trimmed);
        if ($entity === null) {
            throw new NotFoundException('Playback session not found');
        }

        if ($entity->getUserId() !== $userId) {
            throw new NotPermittedException('Playback session belongs to another user');
        }

        $now = time();
        if ($positionSeconds !== null && $durationSeconds !== null && $durationSeconds > 0.0) {
            $this->progressService->saveProgress(
                $userId,
                $entity->getRootKey(),
                $entity->getRelativePath(),
                $positionSeconds,
                $durationSeconds,
                $completed,
                null,
                $trimmed
            );
        }

        if (in_array($entity->getStatus(), ['active', 'paused'], true)) {
            $entity->setStatus('ended');
            $entity->setLastHeartbeatAt($now);
            $entity->setEndedAt($now);
            $this->sessionMapper->update($entity);
        }

        return [
            'sessionToken' => $entity->getSessionToken(),
            'status' => $entity->getStatus(),
            'endedAt' => $entity->getEndedAt() ?? $now,
        ];
    }

    /**
     * Validates that the session token is active, unrevoked, unexpired, owned by $userId, and matches the target video.
     */
    public function validateStreamingSession(
        string $userId,
        string $sessionToken,
        string $rootKey,
        string $relativePath
    ): PlaybackSessionEntity {
        $entity = $this->requireOwnedActiveSession($userId, $sessionToken);
        $cleanRel = '/' . ltrim($this->safeNodeResolver->normalizeRelativePath($relativePath), '/');

        if ($entity->getRootKey() !== $rootKey || $entity->getRelativePath() !== $cleanRel) {
            throw new NotPermittedException('Playback session token does not match requested video');
        }

        return $entity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveSessions(string $userId): array
    {
        $now = time();
        $this->sessionMapper->expireStaleSessions($now, $userId);
        $entities = $this->sessionMapper->findActiveForUser($userId, $now);

        return array_map(fn (PlaybackSessionEntity $e): array => $this->entityToArray($e), $entities);
    }

    public function revokeAllForUser(string $userId): int
    {
        return $this->sessionMapper->revokeAllForUser($userId, time());
    }

    /**
     * @return array{expiredCount: int, purgedCount: int}
     */
    public function cleanupExpiredSessions(?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $expired = $this->sessionMapper->expireStaleSessions($now);
        $purged = $this->sessionMapper->purgeEndedBefore($now - self::RETENTION_SECONDS);

        return [
            'expiredCount' => $expired,
            'purgedCount' => $purged,
        ];
    }

    public function getSessionTtlSeconds(): int
    {
        $val = (int) $this->config->getAppValue(
            Application::APP_ID,
            'session_ttl_seconds',
            (string) self::DEFAULT_TTL_SECONDS
        );
        return max(30, min(86400, $val));
    }

    public function getMaxConcurrentSessions(): int
    {
        $val = (int) $this->config->getAppValue(Application::APP_ID, 'max_concurrent_sessions', '0');
        return max(0, min(100, $val));
    }

    private function requireOwnedActiveSession(string $userId, string $sessionToken): PlaybackSessionEntity
    {
        $trimmed = trim($sessionToken);
        if ($trimmed === '') {
            throw new NotPermittedException('Playback session token is required');
        }

        $entity = $this->sessionMapper->findByToken($trimmed);
        if ($entity === null) {
            throw new NotPermittedException('Invalid playback session token');
        }

        if ($entity->getUserId() !== $userId) {
            throw new NotPermittedException('Playback session belongs to another user');
        }

        $now = time();
        if (!in_array($entity->getStatus(), ['active', 'paused'], true)) {
            throw new NotPermittedException('Playback session is no longer active');
        }

        if ($entity->getExpiresAt() < $now) {
            $entity->setStatus('expired');
            $entity->setEndedAt($now);
            $this->sessionMapper->update($entity);
            throw new NotPermittedException('Playback session has expired');
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function entityToArray(PlaybackSessionEntity $entity): array
    {
        return [
            'sessionToken' => $entity->getSessionToken(),
            'rootId' => $entity->getRootKey(),
            'relativePath' => $entity->getRelativePath(),
            'streamMode' => $entity->getStreamMode(),
            'clientAgent' => $entity->getClientAgent(),
            'status' => $entity->getStatus(),
            'startedAt' => $entity->getStartedAt(),
            'lastHeartbeatAt' => $entity->getLastHeartbeatAt(),
            'expiresAt' => $entity->getExpiresAt(),
        ];
    }
}
