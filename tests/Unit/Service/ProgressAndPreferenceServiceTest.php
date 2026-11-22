<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Service;

use OCA\FolderVideoPlayer\Db\PlaybackProgressEntity;
use OCA\FolderVideoPlayer\Db\PlaybackProgressMapper;
use OCA\FolderVideoPlayer\Db\UserPreferenceEntity;
use OCA\FolderVideoPlayer\Db\UserPreferenceMapper;
use OCA\FolderVideoPlayer\Service\PreferenceService;
use OCA\FolderVideoPlayer\Service\ProgressService;
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
        ]);
        $this->assertSame(1.5, $afterInvalid['playbackRate']);
        $this->assertSame('date_desc', $afterInvalid['sortBy']);
    }
}
