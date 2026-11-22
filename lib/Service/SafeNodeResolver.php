<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Service;

use InvalidArgumentException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class SafeNodeResolver
{
    public function __construct(
        private IRootFolder $rootFolder
    ) {
    }

    public function normalizeFolderPath(string $path): string
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new InvalidArgumentException('Invalid characters in path');
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $segments = explode('/', $trimmed);
        $clean = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException('Path traversal is not allowed');
            }
            $clean[] = $segment;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('Root directory itself cannot be selected as a media folder');
        }

        return '/' . implode('/', $clean);
    }

    public function normalizeRelativePath(string $relativePath): string
    {
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '\\')) {
            throw new InvalidArgumentException('Invalid characters in relative path');
        }

        $trimmed = trim($relativePath);
        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }

        $segments = explode('/', $trimmed);
        $clean = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException('Path traversal is not allowed');
            }
            if (str_starts_with($segment, '.')) {
                throw new NotPermittedException('Hidden files and directories are not accessible');
            }
            $clean[] = $segment;
        }

        return implode('/', $clean);
    }

    /**
     * @param list<array{id: string, path: string, label: string, scope: string}> $effectiveRoots
     * @return array{root: array{id: string, path: string, label: string, scope: string}, folder: Folder}
     */
    public function resolveConfiguredRootFolder(string $userId, string $rootKey, array $effectiveRoots): array
    {
        $trimmedKey = trim($rootKey);
        if ($trimmedKey === '') {
            throw new InvalidArgumentException('Root ID is required');
        }

        $matchedRoot = null;
        foreach ($effectiveRoots as $root) {
            if ($root['id'] === $trimmedKey) {
                $matchedRoot = $root;
                break;
            }
        }

        if ($matchedRoot === null) {
            throw new NotPermittedException('Requested folder root is not configured or accessible');
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $relFromUser = ltrim($matchedRoot['path'], '/');
        if (!$userFolder->nodeExists($relFromUser)) {
            throw new NotFoundException('Configured folder does not exist');
        }

        $node = $userFolder->get($relFromUser);
        if (!$node instanceof Folder || !$node->isReadable()) {
            throw new NotPermittedException('Configured folder is not readable');
        }

        return [
            'root' => $matchedRoot,
            'folder' => $node,
        ];
    }

    /**
     * @param list<array{id: string, path: string, label: string, scope: string}> $effectiveRoots
     * @return array{root: array{id: string, path: string, label: string, scope: string}, rootFolder: Folder, node: Node, relativePath: string}
     */
    public function resolveNodeInRoot(
        string $userId,
        string $rootKey,
        string $relativePath,
        array $effectiveRoots
    ): array {
        $resolvedRoot = $this->resolveConfiguredRootFolder($userId, $rootKey, $effectiveRoots);
        $rootFolderNode = $resolvedRoot['folder'];
        $cleanRelative = $this->normalizeRelativePath($relativePath);

        if ($cleanRelative === '') {
            return [
                'root' => $resolvedRoot['root'],
                'rootFolder' => $rootFolderNode,
                'node' => $rootFolderNode,
                'relativePath' => '',
            ];
        }

        if (!$rootFolderNode->nodeExists($cleanRelative)) {
            throw new NotFoundException('Requested path was not found in configured folder');
        }

        $targetNode = $rootFolderNode->get($cleanRelative);
        if (!$targetNode->isReadable()) {
            throw new NotPermittedException('Requested path is not readable');
        }

        $rootPrefix = rtrim($rootFolderNode->getPath(), '/') . '/';
        $targetPath = $targetNode->getPath();
        if (!str_starts_with($targetPath, $rootPrefix)) {
            throw new NotPermittedException('Resolved node escaped configured root boundary');
        }

        return [
            'root' => $resolvedRoot['root'],
            'rootFolder' => $rootFolderNode,
            'node' => $targetNode,
            'relativePath' => '/' . $cleanRelative,
        ];
    }

    /**
     * @param list<array{id: string, path: string, label: string, scope: string}> $effectiveRoots
     * @return array{root: array{id: string, path: string, label: string, scope: string}, rootFolder: Folder, file: File, relativePath: string}
     */
    public function resolveFileInRoot(
        string $userId,
        string $rootKey,
        string $relativePath,
        array $effectiveRoots
    ): array {
        $resolved = $this->resolveNodeInRoot($userId, $rootKey, $relativePath, $effectiveRoots);
        if (!$resolved['node'] instanceof File) {
            throw new NotFoundException('Requested node is not a file');
        }

        return [
            'root' => $resolved['root'],
            'rootFolder' => $resolved['rootFolder'],
            'file' => $resolved['node'],
            'relativePath' => $resolved['relativePath'],
        ];
    }
}
