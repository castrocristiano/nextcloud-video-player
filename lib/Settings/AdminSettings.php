<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Settings;

use OCA\FolderVideoPlayer\AppInfo\Application;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings
{
    public function __construct(
        private FolderConfigService $folderConfigService,
        private IConfig $config
    ) {
    }

    public function getForm(): TemplateResponse
    {
        Util::addStyle(Application::APP_ID, 'style');
        Util::addScript(Application::APP_ID, 'admin');

        $globalFolders = $this->folderConfigService->getGlobalConfiguredFolders();
        $ffmpegEnabled = $this->config->getAppValue(Application::APP_ID, 'ffmpeg_enabled', '0') === '1';
        $ffmpegPath = $this->config->getAppValue(Application::APP_ID, 'ffmpeg_path', 'ffmpeg');
        $ffprobePath = $this->config->getAppValue(Application::APP_ID, 'ffprobe_path', 'ffprobe');
        $sessionTtl = (int) $this->config->getAppValue(Application::APP_ID, 'session_ttl_seconds', '180');
        $maxConcurrent = (int) $this->config->getAppValue(Application::APP_ID, 'max_concurrent_sessions', '0');

        return new TemplateResponse(Application::APP_ID, 'admin', [
            'globalFolders' => $globalFolders,
            'ffmpegEnabled' => $ffmpegEnabled,
            'ffmpegPath' => $ffmpegPath,
            'ffprobePath' => $ffprobePath,
            'sessionTtlSeconds' => max(30, $sessionTtl),
            'maxConcurrentSessions' => max(0, $maxConcurrent),
        ]);
    }

    public function getSection(): string
    {
        return Application::APP_ID;
    }

    public function getPriority(): int
    {
        return 50;
    }
}
