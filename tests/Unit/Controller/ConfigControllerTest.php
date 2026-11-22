<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Controller;

use OCA\FolderVideoPlayer\Controller\ConfigController;
use OCA\FolderVideoPlayer\Service\FfmpegService;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCP\AppFramework\Http;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ConfigControllerTest extends TestCase
{
    public function testListAddRemoveAndBrowseUserRoots(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'paths' => ['/Movies'],
            'path' => '/Movies',
            default => $d,
        });

        $rootsData = [['id' => 'u1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'user']];
        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')->willReturn($rootsData);
        $folderService->method('addUserFolders')->willReturn($rootsData);
        $folderService->method('removeUserFolder')
            ->willReturnCallback(static function (string $uid, string $rootId) use ($rootsData): array {
                if ($rootId === 'g_global') {
                    throw new NotPermittedException('Pre-configured global folders cannot be removed by users');
                }
                return $rootsData;
            });
        $folderService->method('browseUserDirectories')->willReturn([
            'currentPath' => '/Movies',
            'breadcrumbs' => [['label' => 'Nextcloud', 'path' => '/']],
            'directories' => [['name' => 'SciFi', 'path' => '/Movies/SciFi']],
        ]);

        $ffmpegService = $this->createMock(FfmpegService::class);
        $ffmpegService->method('isTranscodingEnabled')->willReturn(false);

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new ConfigController(
            'folder_video_player',
            $request,
            $folderService,
            $ffmpegService,
            $userSession,
            $l10n
        );

        $listResp = $controller->listRoots();
        $this->assertSame(Http::STATUS_OK, $listResp->getStatus());
        $this->assertCount(1, $listResp->getData()['roots']);

        $addResp = $controller->addUserRoots();
        $this->assertSame(Http::STATUS_CREATED, $addResp->getStatus());

        $delGlobal = $controller->removeUserRoot('g_global');
        $this->assertSame(Http::STATUS_FORBIDDEN, $delGlobal->getStatus());

        $delUser = $controller->removeUserRoot('u1');
        $this->assertSame(Http::STATUS_OK, $delUser->getStatus());

        $browseResp = $controller->browseUserFolders();
        $this->assertSame(Http::STATUS_OK, $browseResp->getStatus());
        $this->assertSame('/Movies', $browseResp->getData()['currentPath']);
    }
}
