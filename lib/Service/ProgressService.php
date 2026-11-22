<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use OCA\FolderVideoPlayer\Db\PlaybackProgressEntity;
use OCA\FolderVideoPlayer\Db\PlaybackProgressMapper;

class ProgressService
{
    public const COMPLETION_THRESHOLD = 0.95;

    public function __construct(
        private PlaybackProgressMapper $progressMapper
    ) {
    }

    /**
     * @return array{rootId: string, relativePath: string, currentTime: float, duration: float, progressRatio: float, completed: bool, updatedAt: int}|null
     */
    public function getVideoProgress(string $userId, string $rootKey, string $relativePath): ?array
    {
        $normRel = $this->normalizeProgressPath($relativePath);
        $pathHash = hash('sha256', $normRel);
        $entity = $this->progressMapper->findByUserAndVideo($userId, $rootKey, $pathHash);
        if ($entity === null) {
            return null;
        }

        return $this->entityToArray($entity);
    }

    /**
     * @return array<string, array{rootId: string, relativePath: string, currentTime: float, duration: float, progressRatio: float, completed: bool, updatedAt: int}>
     */
    public function getProgressMapForRoot(string $userId, string $rootKey): array
    {
        $entities = $this->progressMapper->findAllForUserAndRoot($userId, $rootKey);
        $map = [];
        foreach ($entities as $entity) {
            $map[$entity->getRelativePath()] = $this->entityToArray($entity);
        }
        return $map;
    }

    /**
     * @return array{rootId: string, relativePath: string, currentTime: float, duration: float, progressRatio: float, completed: bool, updatedAt: int}
     */
    public function saveProgress(
        string $userId,
        string $rootKey,
        string $relativePath,
        float $positionSeconds,
        float $durationSeconds,
        bool $forceCompleted = false,
        ?int $fileId = null,
        ?string $sessionToken = null
    ): array {
        $normRel = $this->normalizeProgressPath($relativePath);
        $pathHash = hash('sha256', $normRel);

        $safeDuration = max(0.0, $durationSeconds);
        $safePosition = max(0.0, $positionSeconds);
        $ratio = $safeDuration > 0.0 ? min(1.0, max(0.0, $safePosition / $safeDuration)) : 0.0;
        $completed = $forceCompleted || ($safeDuration > 0.0 && $ratio >= self::COMPLETION_THRESHOLD);

        $storedPosition = $completed ? 0.0 : $safePosition;
        $storedRatio = $completed ? 1.0 : round($ratio, 4);
        $now = time();

        $entity = $this->progressMapper->findByUserAndVideo($userId, $rootKey, $pathHash);
        $isNew = $entity === null;
        if ($entity === null) {
            $entity = new PlaybackProgressEntity();
            $entity->setUserId($userId);
            $entity->setRootKey($rootKey);
            $entity->setRelativePath($normRel);
            $entity->setPathHash($pathHash);
        }

        if ($fileId !== null) {
            $entity->setFileId($fileId);
        }
        $entity->setPositionSeconds(round($storedPosition, 2));
        $entity->setDurationSeconds(round($safeDuration, 2));
        $entity->setProgressRatio($storedRatio);
        $entity->setCompleted($completed);
        if ($sessionToken !== null) {
            $entity->setLastSessionToken($sessionToken);
        }
        $entity->setUpdatedAt($now);

        if ($isNew) {
            $this->progressMapper->insert($entity);
        } else {
            $this->progressMapper->update($entity);
        }

        return $this->entityToArray($entity);
    }

    /**
     * Returns the most recently watched unfinished video belonging to an accessible configured root.
     *
     * @param list<array{id: string, path: string, label: string, scope: string}> $effectiveRoots
     * @return array{rootId: string, rootLabel: string, relativePath: string, folderPath: string, name: string, currentTime: float, duration: float, progressRatio: float, updatedAt: int}|null
     */
    public function getLastWatchedVideo(
        string $userId,
        array $effectiveRoots,
        SafeNodeResolver $resolver
    ): ?array {
        if ($effectiveRoots === []) {
            return null;
        }

        $candidates = $this->progressMapper->findRecentUnfinishedForUser($userId, 10);
        foreach ($candidates as $entity) {
            if ($entity->getCompleted() || $entity->getPositionSeconds() <= 3.0) {
                continue;
            }
            try {
                $resolved = $resolver->resolveFileInRoot(
                    $userId,
                    $entity->getRootKey(),
                    $entity->getRelativePath(),
                    $effectiveRoots
                );
                $file = $resolved['file'];
                $dirName = dirname($resolved['relativePath']);
                $folderPath = ($dirName === '.' || $dirName === DIRECTORY_SEPARATOR) ? '/' : $dirName;

                return [
                    'rootId' => $resolved['root']['id'],
                    'rootLabel' => $resolved['root']['label'],
                    'relativePath' => $resolved['relativePath'],
                    'folderPath' => $folderPath,
                    'name' => $file->getName(),
                    'currentTime' => $entity->getPositionSeconds(),
                    'duration' => $entity->getDurationSeconds(),
                    'progressRatio' => $entity->getProgressRatio(),
                    'updatedAt' => $entity->getUpdatedAt(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function normalizeProgressPath(string $relativePath): string
    {
        $trimmed = trim($relativePath);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }
        return '/' . ltrim($trimmed, '/');
    }

    /**
     * @return array{rootId: string, relativePath: string, currentTime: float, duration: float, progressRatio: float, completed: bool, updatedAt: int}
     */
    private function entityToArray(PlaybackProgressEntity $entity): array
    {
        return [
            'rootId' => $entity->getRootKey(),
            'relativePath' => $entity->getRelativePath(),
            'currentTime' => $entity->getPositionSeconds(),
            'duration' => $entity->getDurationSeconds(),
            'progressRatio' => $entity->getProgressRatio(),
            'completed' => $entity->getCompleted(),
            'updatedAt' => $entity->getUpdatedAt(),
        ];
    }
}
