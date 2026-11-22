<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\ICacheFactory;

class VideoCatalogService
{
    public const DIRECT_VIDEO_EXTENSIONS = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'm4v' => 'video/mp4',
        'mov' => 'video/quicktime',
        'ogv' => 'video/ogg',
    ];

    public const LEGACY_VIDEO_EXTENSIONS = [
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'ts' => 'video/mp2t',
        'rmvb' => 'application/vnd.rn-realmedia-vbr',
        'rm' => 'application/vnd.rn-realmedia',
    ];

    public const MAX_SEARCH_DEPTH = 6;
    public const MAX_SEARCH_RESULTS = 250;

    public function __construct(
        private FolderConfigService $folderConfigService,
        private SafeNodeResolver $safeNodeResolver,
        private SubtitleService $subtitleService,
        private ProgressService $progressService,
        private ?ICacheFactory $cacheFactory = null
    ) {
    }

    /**
     * @return array{root: array{id: string, path: string, label: string, scope: string}, currentPath: string, breadcrumbs: list<array{label: string, path: string}>, folders: list<array<string, mixed>>, videos: list<array<string, mixed>>}
     */
    public function listFolder(
        string $userId,
        string $rootKey,
        string $relativePath = '',
        string $sortBy = 'name_asc',
        bool $forceRefresh = false
    ): array {
        $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
        $resolved = $this->safeNodeResolver->resolveNodeInRoot($userId, $rootKey, $relativePath, $effectiveRoots);
        $targetNode = $resolved['node'];
        if (!$targetNode instanceof Folder) {
            throw new NotFoundException('Requested catalog path is not a folder');
        }

        $rootMeta = $resolved['root'];
        $cleanRel = ltrim($resolved['relativePath'], '/');
        $currentPath = $cleanRel === '' ? '/' : '/' . $cleanRel;
        $progressMap = $this->progressService->getProgressMapForRoot($userId, $rootKey);

        $cache = $this->cacheFactory?->createDistributed('folder_video_player_catalog');
        $cacheKey = sprintf('cat_%s_%s_%s_%s', hash('sha256', $userId), $rootKey, hash('sha256', $currentPath), $sortBy);
        if (!$forceRefresh && $cache !== null) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached) && isset($cached['folders'], $cached['videos'])) {
                foreach ($cached['videos'] as &$videoItem) {
                    $rel = (string) ($videoItem['relativePath'] ?? '');
                    $videoItem['progress'] = $progressMap[$rel] ?? null;
                }
                unset($videoItem);
                return $cached;
            }
        }

        $children = $targetNode->getDirectoryListing();

        $folders = [];
        $videos = [];

        foreach ($children as $child) {
            if (!$child->isReadable()) {
                continue;
            }
            $name = $child->getName();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }

            $childRel = $cleanRel === '' ? '/' . $name : '/' . $cleanRel . '/' . $name;

            if ($child instanceof Folder) {
                $folders[] = [
                    'name' => $name,
                    'relativePath' => $childRel,
                    'rootId' => $rootKey,
                    'modifiedAt' => gmdate('c', $child->getMTime()),
                    'mtime' => $child->getMTime(),
                ];
                continue;
            }

            if ($child instanceof File && $this->isSupportedVideoFilename($name)) {
                $videos[] = $this->buildVideoItem(
                    $child,
                    $rootMeta,
                    $childRel,
                    $cleanRel,
                    $children,
                    $progressMap
                );
            }
        }

        $this->sortItems($folders, $sortBy);
        $this->sortItems($videos, $sortBy);

        $payload = [
            'root' => $rootMeta,
            'currentPath' => $currentPath,
            'breadcrumbs' => $this->buildBreadcrumbs($rootMeta['label'], $cleanRel),
            'folders' => $folders,
            'videos' => $videos,
        ];

        $cache?->set($cacheKey, $payload, 60);

        return $payload;
    }

    /**
     * Searches videos case-insensitively across all active configured roots (or a single specified root).
     *
     * @return array{query: string, videos: list<array<string, mixed>>}
     */
    public function searchVideos(
        string $userId,
        string $query,
        string $sortBy = 'name_asc',
        ?string $filterRootKey = null
    ): array {
        $trimmedQuery = trim($query);
        if ($trimmedQuery === '') {
            return ['query' => '', 'videos' => []];
        }

        $needle = mb_strtolower($trimmedQuery, 'UTF-8');
        $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
        $results = [];

        foreach ($effectiveRoots as $rootMeta) {
            if ($filterRootKey !== null && $filterRootKey !== '' && $rootMeta['id'] !== $filterRootKey) {
                continue;
            }

            try {
                $resolvedRoot = $this->safeNodeResolver->resolveConfiguredRootFolder($userId, $rootMeta['id'], $effectiveRoots);
            } catch (\Throwable) {
                continue;
            }

            $progressMap = $this->progressService->getProgressMapForRoot($userId, $rootMeta['id']);
            $this->searchInFolderRecursive(
                $resolvedRoot['folder'],
                $rootMeta,
                '',
                $needle,
                0,
                $progressMap,
                $results
            );

            if (count($results) >= self::MAX_SEARCH_RESULTS) {
                break;
            }
        }

        $this->sortItems($results, $sortBy);

        return [
            'query' => $trimmedQuery,
            'videos' => $results,
        ];
    }

    public function isSupportedVideoFilename(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return isset(self::DIRECT_VIDEO_EXTENSIONS[$ext]) || isset(self::LEGACY_VIDEO_EXTENSIONS[$ext]);
    }

    public function getMimeTypeForFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return self::DIRECT_VIDEO_EXTENSIONS[$ext]
            ?? self::LEGACY_VIDEO_EXTENSIONS[$ext]
            ?? 'application/octet-stream';
    }

    public function requiresTranscodeFallback(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return isset(self::LEGACY_VIDEO_EXTENSIONS[$ext]);
    }

    /**
     * @param array{id: string, path: string, label: string, scope: string} $rootMeta
     * @param list<\OCP\Files\Node> $siblingNodes
     * @param array<string, array<string, mixed>> $progressMap
     * @return array<string, mixed>
     */
    private function buildVideoItem(
        File $file,
        array $rootMeta,
        string $videoRelPath,
        string $folderRelPath,
        array $siblingNodes,
        array $progressMap
    ): array {
        $name = $file->getName();
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $subtitles = $this->subtitleService->discoverSubtitlesForVideo($name, $folderRelPath, $siblingNodes);

        return [
            'id' => $rootMeta['id'] . ':' . $videoRelPath,
            'fileId' => $file->getId(),
            'rootId' => $rootMeta['id'],
            'rootLabel' => $rootMeta['label'],
            'name' => $name,
            'title' => pathinfo($name, PATHINFO_FILENAME),
            'relativePath' => $videoRelPath,
            'folderPath' => $folderRelPath === '' ? '/' : '/' . $folderRelPath,
            'size' => $file->getSize(),
            'modifiedAt' => gmdate('c', $file->getMTime()),
            'mtime' => $file->getMTime(),
            'extension' => $ext,
            'mimeType' => $this->getMimeTypeForFilename($name),
            'isLegacyFormat' => $this->requiresTranscodeFallback($name),
            'subtitles' => $subtitles,
            'progress' => $progressMap[$videoRelPath] ?? null,
        ];
    }

    /**
     * @param array{id: string, path: string, label: string, scope: string} $rootMeta
     * @param array<string, array<string, mixed>> $progressMap
     * @param list<array<string, mixed>> $results
     */
    private function searchInFolderRecursive(
        Folder $folder,
        array $rootMeta,
        string $currentRelPath,
        string $needle,
        int $depth,
        array $progressMap,
        array &$results
    ): void {
        if ($depth > self::MAX_SEARCH_DEPTH || count($results) >= self::MAX_SEARCH_RESULTS) {
            return;
        }

        try {
            $children = $folder->getDirectoryListing();
        } catch (\Throwable) {
            return;
        }

        foreach ($children as $child) {
            if (count($results) >= self::MAX_SEARCH_RESULTS) {
                return;
            }
            if (!$child->isReadable()) {
                continue;
            }

            $name = $child->getName();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }

            $childRel = $currentRelPath === '' ? $name : $currentRelPath . '/' . $name;

            if ($child instanceof Folder) {
                $this->searchInFolderRecursive(
                    $child,
                    $rootMeta,
                    $childRel,
                    $needle,
                    $depth + 1,
                    $progressMap,
                    $results
                );
                continue;
            }

            if ($child instanceof File && $this->isSupportedVideoFilename($name)) {
                if (str_contains(mb_strtolower($name, 'UTF-8'), $needle)) {
                    $results[] = $this->buildVideoItem(
                        $child,
                        $rootMeta,
                        '/' . $childRel,
                        $currentRelPath,
                        $children,
                        $progressMap
                    );
                }
            }
        }
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private function buildBreadcrumbs(string $rootLabel, string $cleanRelPath): array
    {
        $breadcrumbs = [
            ['label' => $rootLabel, 'path' => '/'],
        ];

        if ($cleanRelPath === '') {
            return $breadcrumbs;
        }

        $accum = '';
        foreach (explode('/', $cleanRelPath) as $segment) {
            if ($segment === '') {
                continue;
            }
            $accum .= '/' . $segment;
            $breadcrumbs[] = [
                'label' => $segment,
                'path' => $accum,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function sortItems(array &$items, string $sortBy): void
    {
        usort($items, static function (array $a, array $b) use ($sortBy): int {
            return match ($sortBy) {
                'name_desc' => strnatcasecmp((string) $b['name'], (string) $a['name']),
                'date_desc' => ((int) ($b['mtime'] ?? 0) <=> (int) ($a['mtime'] ?? 0))
                    ?: strnatcasecmp((string) $a['name'], (string) $b['name']),
                'date_asc' => ((int) ($a['mtime'] ?? 0) <=> (int) ($b['mtime'] ?? 0))
                    ?: strnatcasecmp((string) $a['name'], (string) $b['name']),
                default => strnatcasecmp((string) $a['name'], (string) $b['name']),
            };
        });
    }
}
