<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Tests\Unit\Controller;

use OCA\NextcloudVideoPlayer\Controller\SettingsController;
use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCA\NextcloudVideoPlayer\Settings\AdminSection;
use OCA\NextcloudVideoPlayer\Settings\AdminSettings;
use OCP\AppFramework\Http\Http;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    public function testNonAdminRejectedWithForbidden(): void
    {
        $request = $this->createMock(IRequest::class);
        $folderService = $this->createMock(FolderConfigService::class);
        $config = $this->createMock(IConfig::class);

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('isAdmin')->with('alice')->willReturn(false);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new SettingsController(
            'nextcloud_video_player',
            $request,
            $folderService,
            $config,
            $userSession,
            $groupManager,
            $l10n
        );

        $resp = $controller->saveSettings();
        $this->assertSame(Http::STATUS_FORBIDDEN, $resp->getStatus());
        $this->assertSame(Http::STATUS_FORBIDDEN, $controller->getSettings()->getStatus());
    }

    public function testAdminSavesAndGetsSettingsAndRendersAdminForms(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $k, mixed $d = null): mixed => match ($k) {
            'globalFolders' => [['path' => '/Movies', 'label' => 'Movies']],
            'ffmpegEnabled' => true,
            'ffmpegPath' => '/usr/bin/ffmpeg',
            'ffprobePath' => '/usr/bin/ffprobe',
            'sessionTtlSeconds' => 240,
            'maxConcurrentSessions' => 3,
            default => $d,
        });

        $folderService = $this->createMock(FolderConfigService::class);
        $folderService->method('saveGlobalFolders')
            ->willReturn([['id' => 'g1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']]);
        $folderService->method('getGlobalConfiguredFolders')
            ->willReturn([['id' => 'g1', 'path' => '/Movies', 'label' => 'Movies', 'scope' => 'global']]);

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, string $def = ''): string => match ($key) {
            'ffmpeg_enabled' => '1',
            'session_ttl_seconds' => '240',
            'max_concurrent_sessions' => '3',
            default => $def,
        });

        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin');
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('isLoggedIn')->willReturn(true);
        $userSession->method('getUser')->willReturn($user);

        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('isAdmin')->with('admin')->willReturn(true);

        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        $controller = new SettingsController(
            'nextcloud_video_player',
            $request,
            $folderService,
            $config,
            $userSession,
            $groupManager,
            $l10n
        );

        $saveResp = $controller->saveSettings();
        $this->assertSame(Http::STATUS_OK, $saveResp->getStatus());
        $data = $saveResp->getData();
        $this->assertTrue($data['ffmpegEnabled']);
        $this->assertSame(240, $data['sessionTtlSeconds']);
        $this->assertSame(3, $data['maxConcurrentSessions']);

        $getResp = $controller->getSettings();
        $this->assertSame(Http::STATUS_OK, $getResp->getStatus());

        $urlGen = $this->createMock(IURLGenerator::class);
        $urlGen->method('imagePath')->willReturn('/img/app.svg');
        $section = new AdminSection($l10n, $urlGen);
        $this->assertSame('nextcloud_video_player', $section->getID());
        $this->assertSame('Video Player', $section->getName());
        $this->assertSame(75, $section->getPriority());
        $this->assertSame('/img/app.svg', $section->getIcon());

        $adminSettings = new AdminSettings($folderService, $config);
        $form = $adminSettings->getForm();
        $this->assertSame('admin', $form->getTemplateName());
        $this->assertSame('nextcloud_video_player', $adminSettings->getSection());
        $this->assertSame(50, $adminSettings->getPriority());
    }
}
