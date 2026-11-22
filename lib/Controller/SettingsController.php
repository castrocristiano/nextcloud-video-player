<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\NextcloudVideoPlayer\AppInfo\Application;
use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private FolderConfigService $folderConfigService,
        private IConfig $config,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
        private IL10N $l10n
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoCSRFRequired
     */
    #[NoCSRFRequired]
    public function getSettings(): DataResponse
    {
        $adminCheck = $this->requireAdminUser();
        if ($adminCheck instanceof DataResponse) {
            return $adminCheck;
        }

        return new DataResponse([
            'globalFolders' => $this->folderConfigService->getGlobalConfiguredFolders(),
            'ffmpegEnabled' => $this->config->getAppValue(Application::APP_ID, 'ffmpeg_enabled', '0') === '1',
            'ffmpegPath' => $this->config->getAppValue(Application::APP_ID, 'ffmpeg_path', 'ffmpeg'),
            'ffprobePath' => $this->config->getAppValue(Application::APP_ID, 'ffprobe_path', 'ffprobe'),
            'sessionTtlSeconds' => max(30, (int) $this->config->getAppValue(Application::APP_ID, 'session_ttl_seconds', '180')),
            'maxConcurrentSessions' => max(0, (int) $this->config->getAppValue(Application::APP_ID, 'max_concurrent_sessions', '0')),
        ]);
    }

    public function saveSettings(): DataResponse
    {
        $adminCheck = $this->requireAdminUser();
        if ($adminCheck instanceof DataResponse) {
            return $adminCheck;
        }

        $rawFolders = $this->request->getParam('globalFolders', []);
        if (!is_array($rawFolders)) {
            return $this->errorResponse($this->l10n->t('Invalid global folders payload'), Http::STATUS_BAD_REQUEST);
        }

        try {
            $savedFolders = $this->folderConfigService->saveGlobalFolders($adminCheck, $rawFolders);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        }

        $ffmpegEnabled = filter_var($this->request->getParam('ffmpegEnabled', false), FILTER_VALIDATE_BOOLEAN);
        $ffmpegPath = trim((string) $this->request->getParam('ffmpegPath', 'ffmpeg'));
        $ffprobePath = trim((string) $this->request->getParam('ffprobePath', 'ffprobe'));
        $sessionTtl = max(30, min(86400, (int) $this->request->getParam('sessionTtlSeconds', 180)));
        $maxConcurrent = max(0, min(100, (int) $this->request->getParam('maxConcurrentSessions', 0)));

        $this->config->setAppValue(Application::APP_ID, 'ffmpeg_enabled', $ffmpegEnabled ? '1' : '0');
        $this->config->setAppValue(Application::APP_ID, 'ffmpeg_path', $ffmpegPath !== '' ? $ffmpegPath : 'ffmpeg');
        $this->config->setAppValue(Application::APP_ID, 'ffprobe_path', $ffprobePath !== '' ? $ffprobePath : 'ffprobe');
        $this->config->setAppValue(Application::APP_ID, 'session_ttl_seconds', (string) $sessionTtl);
        $this->config->setAppValue(Application::APP_ID, 'max_concurrent_sessions', (string) $maxConcurrent);

        return new DataResponse([
            'globalFolders' => $savedFolders,
            'ffmpegEnabled' => $ffmpegEnabled,
            'ffmpegPath' => $ffmpegPath !== '' ? $ffmpegPath : 'ffmpeg',
            'ffprobePath' => $ffprobePath !== '' ? $ffprobePath : 'ffprobe',
            'sessionTtlSeconds' => $sessionTtl,
            'maxConcurrentSessions' => $maxConcurrent,
        ]);
    }

    private function requireAdminUser(): string|DataResponse
    {
        if (!$this->userSession->isLoggedIn() || $this->userSession->getUser() === null) {
            return $this->errorResponse($this->l10n->t('Authentication required'), Http::STATUS_UNAUTHORIZED);
        }

        $uid = $this->userSession->getUser()->getUID();
        if (!$this->groupManager->isAdmin($uid)) {
            return $this->errorResponse($this->l10n->t('Administrator privileges required'), Http::STATUS_FORBIDDEN);
        }

        return $uid;
    }

    private function errorResponse(string $message, int $status): DataResponse
    {
        return new DataResponse([
            'error' => [
                'message' => $message,
            ],
        ], $status);
    }
}
