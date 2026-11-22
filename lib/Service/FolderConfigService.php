<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Service;

use InvalidArgumentException;
use OCA\NextcloudVideoPlayer\Db\FolderEntity;
use OCA\NextcloudVideoPlayer\Db\FolderMapper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class FolderConfigService
{
    public function __construct(
        private FolderMapper $folderMapper,
        private IRootFolder $rootFolder,
        private SafeNodeResolver $safeNodeResolver
    ) {
    }

    /**
     * Returns merged and deduplicated roots (global + user) that exist and are readable by the user.
     *
     * @return list<array{id: string, path: string, label: string, scope: string}>
     */
    public function getEffectiveRoots(string $userId): array
    {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $globalEntities = $this->folderMapper->findGlobalFolders();
        $userEntities = $this->folderMapper->findUserFolders($userId);

        $seenHashes = [];
        $roots = [];

        foreach (array_merge($globalEntities, $userEntities) as $entity) {
            $pathHash = $entity->getPathHash();
            if (isset($seenHashes[$pathHash])) {
                continue;
            }

            $folderPath = $entity->getFolderPath();
            $relPath = ltrim($folderPath, '/');
            if ($relPath === '' || !$userFolder->nodeExists($relPath)) {
                continue;
            }

            try {
                $node = $userFolder->get($relPath);
            } catch (\Throwable) {
                continue;
            }

            if (!$node instanceof Folder || !$node->isReadable()) {
                continue;
            }

            $seenHashes[$pathHash] = true;
            $roots[] = [
                'id' => $entity->getRootKey(),
                'path' => $folderPath,
                'label' => $entity->getLabel(),
                'scope' => $entity->getScope(),
            ];
        }

        return $roots;
    }

    /**
     * @return list<array{id: string, path: string, label: string, scope: string}>
     */
    public function getGlobalConfiguredFolders(): array
    {
        $entities = $this->folderMapper->findGlobalFolders();
        $result = [];
        foreach ($entities as $entity) {
            $result[] = [
                'id' => $entity->getRootKey(),
                'path' => $entity->getFolderPath(),
                'label' => $entity->getLabel(),
                'scope' => 'global',
            ];
        }
        return $result;
    }

    /**
     * @param list<array{path?: string, label?: string}|string> $foldersInput
     * @return list<array{id: string, path: string, label: string, scope: string}>
     */
    public function saveGlobalFolders(string $adminUserId, array $foldersInput): array
    {
        $normalizedEntries = [];
        $seenHashes = [];

        foreach ($foldersInput as $item) {
            $rawPath = is_array($item) ? (string) ($item['path'] ?? '') : (string) $item;
            $rawLabel = is_array($item) ? trim((string) ($item['label'] ?? '')) : '';

            $normPath = $this->safeNodeResolver->normalizeFolderPath($rawPath);
            $pathHash = hash('sha256', $normPath);
            if (isset($seenHashes[$pathHash])) {
                continue;
            }
            $seenHashes[$pathHash] = true;

            $label = $rawLabel !== '' ? $rawLabel : basename($normPath);
            $normalizedEntries[] = [
                'path' => $normPath,
                'pathHash' => $pathHash,
                'label' => $label,
            ];
        }

        $this->folderMapper->deleteGlobalFolders();
        $now = time();

        foreach ($normalizedEntries as $entry) {
            $entity = new FolderEntity();
            $entity->setRootKey($this->buildRootKey('global', '', $entry['path']));
            $entity->setScope('global');
            $entity->setUserId('');
            $entity->setFolderPath($entry['path']);
            $entity->setPathHash($entry['pathHash']);
            $entity->setLabel($entry['label']);
            $entity->setCreatedBy($adminUserId);
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
            $this->folderMapper->insert($entity);
        }

        return $this->getGlobalConfiguredFolders();
    }

    /**
     * @param list<string> $paths
     * @return list<array{id: string, path: string, label: string, scope: string}>
     */
    public function addUserFolders(string $userId, array $paths): array
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one folder path must be provided');
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $now = time();

        foreach ($paths as $rawPath) {
            $normPath = $this->safeNodeResolver->normalizeFolderPath((string) $rawPath);
            $relPath = ltrim($normPath, '/');
            if (!$userFolder->nodeExists($relPath)) {
                throw new NotFoundException("Folder not found: {$normPath}");
            }

            $node = $userFolder->get($relPath);
            if (!$node instanceof Folder || !$node->isReadable()) {
                throw new NotPermittedException("Folder is not readable: {$normPath}");
            }

            $pathHash = hash('sha256', $normPath);
            $existing = $this->folderMapper->findByScopeAndPathHash('user', $userId, $pathHash);
            if ($existing !== null) {
                continue;
            }

            $entity = new FolderEntity();
            $entity->setRootKey($this->buildRootKey('user', $userId, $normPath));
            $entity->setScope('user');
            $entity->setUserId($userId);
            $entity->setFolderPath($normPath);
            $entity->setPathHash($pathHash);
            $entity->setLabel(basename($normPath));
            $entity->setCreatedBy($userId);
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
            $this->folderMapper->insert($entity);
        }

        return $this->getEffectiveRoots($userId);
    }

    /**
     * @return list<array{id: string, path: string, label: string, scope: string}>
     */
    public function removeUserFolder(string $userId, string $rootKey): array
    {
        $trimmedKey = trim($rootKey);
        if ($trimmedKey === '') {
            throw new InvalidArgumentException('Root ID is required');
        }

        $entity = $this->folderMapper->findByRootKey($trimmedKey);
        if ($entity === null) {
            throw new NotFoundException('Folder configuration not found');
        }

        if ($entity->getScope() === 'global') {
            throw new NotPermittedException('Pre-configured global folders cannot be removed by users');
        }

        if ($entity->getUserId() !== $userId) {
            throw new NotPermittedException('You do not have permission to remove this folder');
        }

        $this->folderMapper->delete($entity);
        return $this->getEffectiveRoots($userId);
    }

    /**
     * Lists subdirectories inside the authenticated user's Nextcloud root for the visual folder picker.
     *
     * @return array{currentPath: string, breadcrumbs: list<array{label: string, path: string}>, directories: list<array{name: string, path: string}>}
     */
    public function browseUserDirectories(string $userId, string $relativePath = ''): array
    {
        $cleanRel = $this->safeNodeResolver->normalizeRelativePath($relativePath);
        $userFolder = $this->rootFolder->getUserFolder($userId);

        if ($cleanRel === '') {
            $targetFolder = $userFolder;
            $currentPath = '/';
        } else {
            if (!$userFolder->nodeExists($cleanRel)) {
                throw new NotFoundException('Directory not found');
            }
            $node = $userFolder->get($cleanRel);
            if (!$node instanceof Folder || !$node->isReadable()) {
                throw new NotPermittedException('Directory is not readable');
            }
            $targetFolder = $node;
            $currentPath = '/' . $cleanRel;
        }

        $directories = [];
        foreach ($targetFolder->getDirectoryListing() as $child) {
            if (!$child instanceof Folder || !$child->isReadable()) {
                continue;
            }
            $name = $child->getName();
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }
            $childPath = $cleanRel === '' ? '/' . $name : '/' . $cleanRel . '/' . $name;
            $directories[] = [
                'name' => $name,
                'path' => $childPath,
            ];
        }

        usort(
            $directories,
            static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name'])
        );

        $breadcrumbs = [
            ['label' => 'Nextcloud', 'path' => '/'],
        ];
        if ($cleanRel !== '') {
            $accum = '';
            foreach (explode('/', $cleanRel) as $part) {
                $accum .= '/' . $part;
                $breadcrumbs[] = [
                    'label' => $part,
                    'path' => $accum,
                ];
            }
        }

        return [
            'currentPath' => $currentPath,
            'breadcrumbs' => $breadcrumbs,
            'directories' => $directories,
        ];
    }

    private function buildRootKey(string $scope, string $userId, string $normalizedPath): string
    {
        return substr(hash('sha256', "{$scope}:{$userId}:{$normalizedPath}"), 0, 16);
    }
}
