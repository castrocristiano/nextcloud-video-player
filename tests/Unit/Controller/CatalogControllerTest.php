<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Controller;

use OCA\NextcloudVideoPlayer\Controller\CatalogController;
use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCA\NextcloudVideoPlayer\Service\SafeNodeResolver;
use OCA\NextcloudVideoPlayer\Service\SubtitleService;
use OCA\NextcloudVideoPlayer\Service\VideoCatalogService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class CatalogControllerTest extends TestCase
{
    public function testListFolderSearchAndServeSubtitle(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'rootId' => 'rk1',
            'path' => '/Film.pt-BR.srt',
            'q' => 'Film',
            'sort' => 'name_asc',
            default => $d,
        });

        $catalogService = $this->createMock(VideoCatalogService::class);
        $catalogService->method('listFolder')->willReturn([
            'root' => ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'],
            'currentPath' => '/',
            'breadcrumbs' => [['label' => 'Movies', 'path' => '/']],
            'folders' => [],
            'videos' => [],
        ]);
        $catalogService->method('searchVideos')->willReturn([
            'query' => 'Film',
            'videos' => [['name' => 'Film.mp4']],
        ]);

        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')->willReturn([
            ['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global'],
        ]);

        $resolver = $this->createMock(SafeNodeResolver::class);
        $subtitleService = $this->createMock(SubtitleService::class);
        $subtitleService->method('getWebVttForPath')->willReturn("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nSub\n");

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new CatalogController(
            'nextcloud_video_player',
            $request,
            $catalogService,
            $folderService,
            $resolver,
            $subtitleService,
            $userSession,
            $l10n
        );

        $listResp = $controller->listFolder();
        $this->assertSame(Http::STATUS_OK, $listResp->getStatus());

        $searchResp = $controller->search();
        $this->assertSame(Http::STATUS_OK, $searchResp->getStatus());
        $this->assertCount(1, $searchResp->getData()['videos']);

        $subResp = $controller->serveSubtitle('rk1');
        $this->assertSame(Http::STATUS_OK, $subResp->getStatus());
        $this->assertSame('text/vtt; charset=utf-8', $subResp->getHeaders()['Content-Type']);
        $this->assertStringStartsWith('WEBVTT', $subResp->render());
    }
}
