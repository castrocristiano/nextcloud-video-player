<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use OCA\FolderVideoPlayer\Db\FolderEntity;
use OCA\FolderVideoPlayer\Db\FolderMapper;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use PHPUnit\Framework\TestCase;

class FolderConfigServiceTest extends TestCase
{
    public function testGetEffectiveRootsMergesDeduplicatesAndFiltersUnreadable(): void
    {
        $global1 = FolderEntity::fromRow([
            'id' => 1,
            'root_key' => 'g_movies',
            'scope' => 'global',
            'user_id' => '',
            'folder_path' => '/Movies',
            'path_hash' => hash('sha256', '/Movies'),
            'label' => 'Cinema',
        ]);
        $globalRestricted = FolderEntity::fromRow([
            'id' => 2,
            'root_key' => 'g_training',
            'scope' => 'global',
            'user_id' => '',
            'folder_path' => '/Training',
            'path_hash' => hash('sha256', '/Training'),
            'label' => 'Training',
        ]);
        $userDup = FolderEntity::fromRow([
            'id' => 3,
            'root_key' => 'u_movies',
            'scope' => 'user',
            'user_id' => 'alice',
            'folder_path' => '/Movies',
            'path_hash' => hash('sha256', '/Movies'),
            'label' => 'Movies',
        ]);
        $userPersonal = FolderEntity::fromRow([
            'id' => 4,
            'root_key' => 'u_clips',
            'scope' => 'user',
            'user_id' => 'alice',
            'folder_path' => '/Clips',
            'path_hash' => hash('sha256', '/Clips'),
            'label' => 'Clips',
        ]);

        $mapper = $this->createMock(FolderMapper::class);
        $mapper->method('findGlobalFolders')->willReturn([$global1, $globalRestricted]);
        $mapper->method('findUserFolders')->with('alice')->willReturn([$userDup, $userPersonal]);

        $readableFolder = $this->createMock(Folder::class);
        $readableFolder->method('isReadable')->willReturn(true);

        $unreadableFolder = $this->createMock(Folder::class);
        $unreadableFolder->method('isReadable')->willReturn(false);

        $userRoot = $this->createMock(Folder::class);
        $userRoot->method('nodeExists')
            ->willReturnCallback(static fn (string $rel): bool => in_array($rel, ['Movies', 'Training', 'Clips'], true));
        $userRoot->method('get')
            ->willReturnCallback(static fn (string $rel): Folder => $rel === 'Training' ? $unreadableFolder : $readableFolder);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userRoot);

        $service = new FolderConfigService($mapper, $rootFolder, new SafeNodeResolver($rootFolder));
        $roots = $service->getEffectiveRoots('alice');

        $this->assertCount(2, $roots);
        $this->assertSame('g_movies', $roots[0]['id']);
        $this->assertSame('global', $roots[0]['scope']);
        $this->assertSame('u_clips', $roots[1]['id']);
        $this->assertSame('user', $roots[1]['scope']);
    }

    public function testPreventUserFromRemovingGlobalFolder(): void
    {
        $globalEntity = FolderEntity::fromRow([
            'id' => 1,
            'root_key' => 'g_movies',
            'scope' => 'global',
            'user_id' => '',
            'folder_path' => '/Movies',
            'path_hash' => hash('sha256', '/Movies'),
            'label' => 'Movies',
        ]);

        $mapper = $this->createMock(FolderMapper::class);
        $mapper->method('findByRootKey')->with('g_movies')->willReturn($globalEntity);

        $rootFolder = $this->createMock(IRootFolder::class);
        $service = new FolderConfigService($mapper, $rootFolder, new SafeNodeResolver($rootFolder));

        $this->expectException(NotPermittedException::class);
        $service->removeUserFolder('alice', 'g_movies');
    }

    public function testAddRemoveUserFolderAndBrowseDirectories(): void
    {
        $inserted = [];
        $mapper = $this->createMock(FolderMapper::class);
        $mapper->method('findByScopeAndPathHash')->willReturn(null);
        $mapper->method('insert')
            ->willReturnCallback(static function (FolderEntity $e) use (&$inserted): FolderEntity {
                $inserted[] = $e;
                return $e;
            });
        $mapper->method('findGlobalFolders')->willReturn([]);
        $mapper->method('findUserFolders')->willReturnCallback(static function () use (&$inserted): array {
            return $inserted;
        });

        $subDirA = $this->createMock(Folder::class);
        $subDirA->method('isReadable')->willReturn(true);
        $subDirA->method('getName')->willReturn('Series');

        $hiddenDir = $this->createMock(Folder::class);
        $hiddenDir->method('isReadable')->willReturn(true);
        $hiddenDir->method('getName')->willReturn('.trash');

        $userRoot = $this->createMock(Folder::class);
        $userRoot->method('nodeExists')->with('Series')->willReturn(true);
        $userRoot->method('get')->with('Series')->willReturn($subDirA);
        $userRoot->method('getDirectoryListing')->willReturn([$subDirA, $hiddenDir]);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userRoot);

        $service = new FolderConfigService($mapper, $rootFolder, new SafeNodeResolver($rootFolder));
        $roots = $service->addUserFolders('alice', ['/Series']);
        $this->assertCount(1, $roots);
        $this->assertSame('/Series', $roots[0]['path']);

        $browse = $service->browseUserDirectories('alice', '');
        $this->assertSame('/', $browse['currentPath']);
        $this->assertCount(1, $browse['directories']);
        $this->assertSame('/Series', $browse['directories'][0]['path']);

        $mapper->method('findByRootKey')->with($roots[0]['id'])->willReturn($inserted[0]);
        $mapper->expects($this->once())->method('delete');
        $service->removeUserFolder('alice', $roots[0]['id']);
    }

    public function testSaveGlobalFoldersDeduplicatesAndPersists(): void
    {
        $saved = [];
        $mapper = $this->createMock(FolderMapper::class);
        $mapper->expects($this->once())->method('deleteGlobalFolders');
        $mapper->method('insert')
            ->willReturnCallback(static function (FolderEntity $e) use (&$saved): FolderEntity {
                $saved[] = $e;
                return $e;
            });
        $mapper->method('findGlobalFolders')->willReturnCallback(static function () use (&$saved): array {
            return $saved;
        });

        $rootFolder = $this->createMock(IRootFolder::class);
        $service = new FolderConfigService($mapper, $rootFolder, new SafeNodeResolver($rootFolder));

        $result = $service->saveGlobalFolders('admin', [
            ['path' => '/Movies', 'label' => 'Movies Library'],
            ['path' => '/Movies/', 'label' => 'Duplicate'],
            '/Documentaries',
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('Movies Library', $result[0]['label']);
        $this->assertSame('Documentaries', $result[1]['label']);
    }
}
