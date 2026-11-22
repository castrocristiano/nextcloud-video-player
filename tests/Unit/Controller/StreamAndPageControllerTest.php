<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Tests\Unit\Controller;

use OCA\FolderVideoPlayer\Controller\PageController;
use OCA\FolderVideoPlayer\Controller\StreamController;
use OCA\FolderVideoPlayer\Db\PlaybackSessionEntity;
use OCA\FolderVideoPlayer\Service\FfmpegService;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCA\FolderVideoPlayer\Service\MediaStreamService;
use OCA\FolderVideoPlayer\Service\PlaybackSessionService;
use OCA\FolderVideoPlayer\Service\ProgressService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCA\FolderVideoPlayer\Service\SubtitleService;
use OCA\FolderVideoPlayer\Service\VideoCatalogService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class StreamAndPageControllerTest extends TestCase
{
    public function testStreamReleasesPhpSessionLockAndReturns206PartialContent(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'path' => '/Matrix.mp4',
            'sessionToken' => 'tok_valid',
            default => $d,
        });
        $request->method('getHeader')->with('Range')->willReturn('bytes=4-9');

        $roots = [['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']];
        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')->willReturn($roots);

        $memStream = fopen('php://memory', 'r+');
        $this->assertIsResource($memStream);
        fwrite($memStream, '0123456789ABCDEF');
        rewind($memStream);

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('Matrix.mp4');
        $file->method('getSize')->willReturn(16);
        $file->method('fopen')->with('r')->willReturn($memStream);
        $file->method('getInternalPath')->willReturn('/tmp/Matrix.mp4');

        $folderNode = $this->createMock(Folder::class);
        $resolver = $this->createMock(SafeNodeResolver::class);
        $resolver->method('resolveFileInRoot')->willReturn([
            'root' => $roots[0],
            'rootFolder' => $folderNode,
            'file' => $file,
            'relativePath' => '/Matrix.mp4',
        ]);

        $catalogService = new VideoCatalogService(
            $folderService,
            $resolver,
            new SubtitleService(),
            $this->createMock(ProgressService::class)
        );

        $ffmpegService = $this->createMock(FfmpegService::class);
        $ffmpegService->method('isTranscodingEnabled')->willReturn(true);
        $ffmpegService->method('probeStreamHeader')->willReturn([
            'duration' => 120.0,
            'videoCodec' => 'h264',
            'audioCodec' => 'aac',
            'canTranscode' => true,
        ]);
        $ffmpegService->method('probeLocalFile')->willReturn([
            'duration' => 120.0,
            'videoCodec' => 'h264',
            'audioCodec' => 'aac',
            'canTranscode' => true,
        ]);

        $sessionService = $this->createMock(PlaybackSessionService::class);
        $sessionService->expects($this->once())
            ->method('validateStreamingSession')
            ->with('alice', 'tok_valid', 'rk1', '/Matrix.mp4')
            ->willReturn(new PlaybackSessionEntity());

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $phpSession = $this->createMock(ISession::class);
        $phpSession->expects($this->atLeastOnce())->method('close');

        $preview = $this->createMock(IPreview::class);
        $preview->method('getPreview')->willReturn(null);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new StreamController(
            'folder_video_player',
            $request,
            $folderService,
            $resolver,
            $catalogService,
            new MediaStreamService(),
            $ffmpegService,
            $sessionService,
            $userSession,
            $phpSession,
            $preview,
            $l10n
        );

        $streamResp = $controller->stream('rk1');
        $this->assertSame(Http::STATUS_PARTIAL_CONTENT, $streamResp->getStatus());
        $this->assertSame('bytes 4-9/16', $streamResp->getHeaders()['Content-Range']);
        $this->assertInstanceOf(ICallbackResponse::class, $streamResp);

        $buffer = '';
        $output = $this->createMock(IOutput::class);
        $output->method('setOutput')->willReturnCallback(static function (string $chunk) use (&$buffer): void {
            $buffer .= $chunk;
        });
        $streamResp->callback($output);
        $this->assertSame('456789', $buffer);

        $probeResp = $controller->probe('rk1');
        $this->assertSame(Http::STATUS_OK, $probeResp->getStatus());
        $this->assertSame(120.0, $probeResp->getData()['duration']);

        $thumbResp = $controller->thumbnail('rk1');
        $this->assertSame(Http::STATUS_OK, $thumbResp->getStatus());
        $this->assertSame('image/svg+xml', $thumbResp->getHeaders()['Content-Type']);
    }

    public function testPageControllerConfiguresStrictCsp(): void
    {
        $request = $this->createMock(IRequest::class);
        $pageController = new PageController('folder_video_player', $request);
        $resp = $pageController->index();

        $this->assertSame('main', $resp->getTemplateName());
        $csp = $resp->getContentSecurityPolicy();
        $this->assertNotNull($csp);
        $this->assertContains("'self'", $csp->getAllowedMediaDomains());
        $this->assertContains('blob:', $csp->getAllowedMediaDomains());
    }

    public function testProbeUsesDistributedCacheHitWithoutRunningFfprobe(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'path' => '/Wave.avi',
            default => $d,
        });

        $roots = [['id' => 'rk1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']];
        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('getEffectiveRoots')->willReturn($roots);

        $file = $this->createMock(File::class);
        $file->method('getName')->willReturn('Wave.avi');
        $file->method('getSize')->willReturn(732882944);
        $file->method('getMTime')->willReturn(1222256714);

        $folderNode = $this->createMock(Folder::class);
        $resolver = $this->createMock(SafeNodeResolver::class);
        $resolver->method('resolveFileInRoot')->willReturn([
            'root' => $roots[0],
            'rootFolder' => $folderNode,
            'file' => $file,
            'relativePath' => '/Wave.avi',
        ]);

        $catalogService = new VideoCatalogService(
            $folderService,
            $resolver,
            new SubtitleService(),
            $this->createMock(ProgressService::class)
        );

        $ffmpegService = $this->createMock(FfmpegService::class);
        $ffmpegService->method('isTranscodingEnabled')->willReturn(true);
        $ffmpegService->expects($this->never())->method('probeStreamHeader');
        $ffmpegService->expects($this->never())->method('probeLocalFile');

        $cache = $this->createMock(ICache::class);
        $cache->method('get')->willReturn([
            'duration' => 6144.84,
            'videoCodec' => 'mpeg4',
            'audioCodec' => 'mp3',
            'canTranscode' => true,
        ]);
        $cacheFactory = $this->createMock(ICacheFactory::class);
        $cacheFactory->method('createDistributed')->willReturn($cache);

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new StreamController(
            'folder_video_player',
            $request,
            $folderService,
            $resolver,
            $catalogService,
            new MediaStreamService(),
            $ffmpegService,
            $this->createMock(PlaybackSessionService::class),
            $userSession,
            $this->createMock(ISession::class),
            $this->createMock(IPreview::class),
            $l10n,
            $cacheFactory
        );

        $resp = $controller->probe('rk1');
        $this->assertSame(Http::STATUS_OK, $resp->getStatus());
        $this->assertSame(6144.84, $resp->getData()['duration']);
    }
}
