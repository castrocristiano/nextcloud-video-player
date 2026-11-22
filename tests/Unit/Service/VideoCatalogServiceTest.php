<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Service;

use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCA\NextcloudVideoPlayer\Service\ProgressService;
use OCA\NextcloudVideoPlayer\Service\SafeNodeResolver;
use OCA\NextcloudVideoPlayer\Service\SubtitleService;
use OCA\NextcloudVideoPlayer\Service\VideoCatalogService;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\TestCase;

class VideoCatalogServiceTest extends TestCase
{
    public function testListFolderFiltersNonVideoAndHiddenFilesAndSearchesCaseInsensitively(): void
    {
        $roots = [['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']];

        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')->willReturn($roots);

        $subFolder = $this->createMock(Folder::class);
        $subFolder->method('isReadable')->willReturn(true);
        $subFolder->method('getName')->willReturn('SciFi');
        $subFolder->method('getMTime')->willReturn(1700000100);
        $subFolder->method('getDirectoryListing')->willReturn([]);

        $mp4File = $this->createMock(File::class);
        $mp4File->method('isReadable')->willReturn(true);
        $mp4File->method('getId')->willReturn(101);
        $mp4File->method('getName')->willReturn('Interstellar.mp4');
        $mp4File->method('getSize')->willReturn(104857600);
        $mp4File->method('getMTime')->willReturn(1700000500);

        $aviFile = $this->createMock(File::class);
        $aviFile->method('isReadable')->willReturn(true);
        $aviFile->method('getId')->willReturn(102);
        $aviFile->method('getName')->willReturn('ClassicFilm.avi');
        $aviFile->method('getSize')->willReturn(52428800);
        $aviFile->method('getMTime')->willReturn(1700000200);

        $pdfFile = $this->createMock(File::class);
        $pdfFile->method('isReadable')->willReturn(true);
        $pdfFile->method('getName')->willReturn('manual.pdf');

        $hiddenVideo = $this->createMock(File::class);
        $hiddenVideo->method('isReadable')->willReturn(true);
        $hiddenVideo->method('getName')->willReturn('.secret.mp4');

        $rootFolderNode = $this->createMock(Folder::class);
        $rootFolderNode->method('isReadable')->willReturn(true);
        $rootFolderNode->method('getDirectoryListing')->willReturn([
            $subFolder,
            $mp4File,
            $aviFile,
            $pdfFile,
            $hiddenVideo,
        ]);

        $resolver = $this->createMock(SafeNodeResolver::class);
        $resolver->method('resolveNodeInRoot')->willReturn([
            'root' => $roots[0],
            'rootFolder' => $rootFolderNode,
            'node' => $rootFolderNode,
            'relativePath' => '',
        ]);
        $resolver->method('resolveConfiguredRootFolder')->willReturn([
            'root' => $roots[0],
            'folder' => $rootFolderNode,
        ]);

        $progressService = $this->createMock(ProgressService::class);
        $progressService->method('getProgressMapForRoot')->willReturn([
            '/Interstellar.mp4' => [
                'rootId' => 'rk1',
                'relativePath' => '/Interstellar.mp4',
                'currentTime' => 600.0,
                'duration' => 1800.0,
                'progressRatio' => 0.3333,
                'completed' => false,
                'updatedAt' => 1700000600,
            ],
        ]);

        $catalogService = new VideoCatalogService(
            $folderService,
            $resolver,
            new SubtitleService(),
            $progressService
        );

        $listed = $catalogService->listFolder('alice', 'rk1', '', 'name_asc');
        $this->assertCount(1, $listed['folders']);
        $this->assertSame('SciFi', $listed['folders'][0]['name']);
        $this->assertCount(2, $listed['videos']);
        $this->assertSame('ClassicFilm.avi', $listed['videos'][0]['name']);
        $this->assertTrue($listed['videos'][0]['isLegacyFormat']);
        $this->assertSame('Interstellar.mp4', $listed['videos'][1]['name']);
        $this->assertFalse($listed['videos'][1]['isLegacyFormat']);
        $this->assertNotNull($listed['videos'][1]['progress']);

        // Search case-insensitively
        $search = $catalogService->searchVideos('alice', 'interSTELLAR', 'date_desc');
        $this->assertCount(1, $search['videos']);
        $this->assertSame('Interstellar.mp4', $search['videos'][0]['name']);
    }
}
