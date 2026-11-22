<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\FolderVideoPlayer\AppInfo\Application;
use OCA\FolderVideoPlayer\Service\FfmpegService;
use OCA\FolderVideoPlayer\Service\FolderConfigService;
use OCA\FolderVideoPlayer\Service\ProgressService;
use OCA\FolderVideoPlayer\Service\SafeNodeResolver;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class ConfigController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private FolderConfigService $folderConfigService,
        private FfmpegService $ffmpegService,
        private IUserSession $userSession,
        private IL10N $l10n,
        private ?ProgressService $progressService = null,
        private ?SafeNodeResolver $safeNodeResolver = null
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function listRoots(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
        $lastWatched = null;
        if ($this->progressService !== null && $this->safeNodeResolver !== null) {
            $lastWatched = $this->progressService->getLastWatchedVideo(
                $userId,
                $effectiveRoots,
                $this->safeNodeResolver
            );
        }

        return new DataResponse([
            'roots' => $effectiveRoots,
            'lastWatched' => $lastWatched,
            'browseBase' => [
                'enabled' => true,
                'label' => $this->l10n->t('Nextcloud Files'),
            ],
            'capabilities' => [
                'ffmpegEnabled' => $this->ffmpegService->isTranscodingEnabled(),
            ],
            'meta' => [
                'appId' => Application::APP_ID,
                'version' => Application::APP_VERSION,
                'userId' => $userId,
            ],
        ]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function addUserRoots(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $paths = $this->request->getParam('paths', []);
        if (!is_array($paths)) {
            return $this->errorResponse($this->l10n->t('Invalid paths list'), Http::STATUS_BAD_REQUEST);
        }

        try {
            $stringPaths = array_values(array_map('strval', $paths));
            $roots = $this->folderConfigService->addUserFolders($userId, $stringPaths);
            return new DataResponse(['roots' => $roots], Http::STATUS_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function removeUserRoot(string $rootId): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        try {
            $roots = $this->folderConfigService->removeUserFolder($userId, $rootId);
            return new DataResponse(['roots' => $roots]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function browseUserFolders(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $path = (string) $this->request->getParam('path', '');
        try {
            $listing = $this->folderConfigService->browseUserDirectories($userId, $path);
            return new DataResponse($listing);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        } catch (NotPermittedException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_NOT_FOUND);
        }
    }

    private function requireAuthenticatedUserId(): string|DataResponse
    {
        if (!$this->userSession->isLoggedIn() || $this->userSession->getUser() === null) {
            return $this->errorResponse($this->l10n->t('Authentication required'), Http::STATUS_UNAUTHORIZED);
        }

        return $this->userSession->getUser()->getUID();
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
