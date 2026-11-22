<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use OCA\FolderVideoPlayer\Db\PlaybackProgressEntity;
use OCA\FolderVideoPlayer\Db\PlaybackProgressMapper;
use OCA\FolderVideoPlayer\Db\UserPreferenceEntity;
use OCA\FolderVideoPlayer\Db\UserPreferenceMapper;
use OCA\FolderVideoPlayer\Service\PreferenceService;
use OCA\FolderVideoPlayer\Service\ProgressService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;

class ProgressAndPreferenceServiceTest extends TestCase
{
    public function testSaveProgressResetsPositionAt95PercentCompletion(): void
    {
        $stored = null;
        $mapper = $this->createMock(PlaybackProgressMapper::class);
        $mapper->method('findByUserAndVideo')->willReturnCallback(static function () use (&$stored): ?PlaybackProgressEntity {
            return $stored;
        });
        $mapper->method('insert')->willReturnCallback(static function (PlaybackProgressEntity $e) use (&$stored): PlaybackProgressEntity {
            $stored = $e;
            return $e;
        });
        $mapper->method('update')->willReturnCallback(static function (PlaybackProgressEntity $e) use (&$stored): PlaybackProgressEntity {
            $stored = $e;
            return $e;
        });
        $mapper->method('findAllForUserAndRoot')->willReturnCallback(static function () use (&$stored): array {
            return $stored !== null ? [$stored] : [];
        });

        $service = new ProgressService($mapper);

        $partial = $service->saveProgress('alice', 'rk1', 'Matrix.mp4', 300.0, 1200.0, false, 10, 'tok1');
        $this->assertFalse($partial['completed']);
        $this->assertSame(300.0, $partial['currentTime']);
        $this->assertSame(0.25, $partial['progressRatio']);

        $fetched = $service->getVideoProgress('alice', 'rk1', '/Matrix.mp4');
        $this->assertNotNull($fetched);
        $this->assertSame(300.0, $fetched['currentTime']);

        $map = $service->getProgressMapForRoot('alice', 'rk1');
        $this->assertArrayHasKey('/Matrix.mp4', $map);

        $completed = $service->saveProgress('alice', 'rk1', '/Matrix.mp4', 1150.0, 1200.0);
        $this->assertTrue($completed['completed']);
        $this->assertSame(0.0, $completed['currentTime']);
        $this->assertSame(1.0, $completed['progressRatio']);
    }

    public function testPreferencesDefaultAndUpdateValidation(): void
    {
        $stored = null;
        $mapper = $this->createMock(UserPreferenceMapper::class);
        $mapper->method('findByUserId')->willReturnCallback(static function () use (&$stored): ?UserPreferenceEntity {
            return $stored;
        });
        $mapper->method('insert')->willReturnCallback(static function (UserPreferenceEntity $e) use (&$stored): UserPreferenceEntity {
            $stored = $e;
            return $e;
        });
        $mapper->method('update')->willReturnCallback(static function (UserPreferenceEntity $e) use (&$stored): UserPreferenceEntity {
            $stored = $e;
            return $e;
        });

        $service = new PreferenceService($mapper);
        $defaults = $service->getPreferences('alice');
        $this->assertSame(1.0, $defaults['playbackRate']);
        $this->assertTrue($defaults['autoPlayNext']);
        $this->assertSame('name_asc', $defaults['sortBy']);

        $updated = $service->updatePreferences('alice', [
            'playbackRate' => 1.5,
            'autoPlayNext' => false,
            'sortBy' => 'date_desc',
            'volume' => 0.65,
            'muted' => true,
            'preferredSubtitle' => 'pt-BR',
        ]);
        $this->assertSame(1.5, $updated['playbackRate']);
        $this->assertFalse($updated['autoPlayNext']);
        $this->assertSame('date_desc', $updated['sortBy']);
        $this->assertSame(0.65, $updated['volume']);
        $this->assertTrue($updated['muted']);
        $this->assertSame('pt-BR', $updated['preferredSubtitle']);

        // Invalid playbackRate or sortBy are ignored
        $afterInvalid = $service->updatePreferences('alice', [
            'playbackRate' => 99.0,
            'sortBy' => 'invalid_sort',
            'subtitleScale' => 5.0,
            'lastRootId' => 'rk_main',
            'lastFolderPath' => '/Series/Season1',
        ]);
        $this->assertSame(1.5, $afterInvalid['playbackRate']);
        $this->assertSame('date_desc', $afterInvalid['sortBy']);
        $this->assertSame(2.0, $afterInvalid['subtitleScale']);
        $this->assertSame('rk_main', $afterInvalid['lastRootId']);
        $this->assertSame('/Series/Season1', $afterInvalid['lastFolderPath']);

        $afterTraversal = $service->updatePreferences('alice', [
            'subtitleScale' => 0.1,
            'lastFolderPath' => '/Series/../../etc',
        ]);
        $this->assertSame(0.75, $afterTraversal['subtitleScale']);
        $this->assertNull($afterTraversal['lastFolderPath']);
    }

    public function testGetLastWatchedVideoReturnsMostRecentAccessibleUnfinishedVideo(): void
    {
        $entity = new PlaybackProgressEntity();
        $entity->setUserId('alice');
        $entity->setRootKey('rk1');
        $entity->setRelativePath('/Movies/Wave.avi');
        $entity->setPositionSeconds(420.0);
        $entity->setDurationSeconds(3600.0);
        $entity->setProgressRatio(0.1167);
        $entity->setCompleted(false);
        $entity->setUpdatedAt(1700000100);

        $mapper = $this->createMock(PlaybackProgressMapper::class);
        $mapper->method('findRecentUnfinishedForUser')->with('alice', 10)->willReturn([$entity]);

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('Wave.avi');
        $file->method('getPath')->willReturn('/alice/files/Media/Movies/Wave.avi');
        $file->method('isReadable')->willReturn(true);

        $rootFolderNode = $this->createMock(Folder::class);
        $rootFolderNode->method('getPath')->willReturn('/alice/files/Media');
        $rootFolderNode->method('isReadable')->willReturn(true);
        $rootFolderNode->method('nodeExists')->with('Movies/Wave.avi')->willReturn(true);
        $rootFolderNode->method('get')->with('Movies/Wave.avi')->willReturn($file);

        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('nodeExists')->with('Media')->willReturn(true);
        $userFolder->method('get')->with('Media')->willReturn($rootFolderNode);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

        $resolver = new SafeNodeResolver($rootFolder);
        $service = new ProgressService($mapper);

        $roots = [['id' => 'rk1', 'path' => '/Media', 'label' => 'Media', 'scope' => 'user']];
        $lastWatched = $service->getLastWatchedVideo('alice', $roots, $resolver);

        $this->assertNotNull($lastWatched);
        $this->assertSame('rk1', $lastWatched['rootId']);
        $this->assertSame('/Movies/Wave.avi', $lastWatched['relativePath']);
        $this->assertSame('/Movies', $lastWatched['folderPath']);
        $this->assertSame('Wave.avi', $lastWatched['name']);
        $this->assertSame(420.0, $lastWatched['currentTime']);
    }
}
