<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\NextcloudVideoPlayer\Service\FolderConfigService;
use OCA\NextcloudVideoPlayer\Service\SafeNodeResolver;
use OCA\NextcloudVideoPlayer\Service\SubtitleService;
use OCA\NextcloudVideoPlayer\Service\VideoCatalogService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Http;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class CatalogController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private VideoCatalogService $catalogService,
        private FolderConfigService $folderConfigService,
        private SafeNodeResolver $safeNodeResolver,
        private SubtitleService $subtitleService,
        private IUserSession $userSession,
        private IL10N $l10n
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function listFolder(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $rootId = (string) $this->request->getParam('rootId', '');
        $path = (string) $this->request->getParam('path', '');
        $sortBy = (string) $this->request->getParam('sort', 'name_asc');

        try {
            $data = $this->catalogService->listFolder($userId, $rootId, $path, $sortBy);
            return new DataResponse($data);
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
    public function search(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $query = (string) $this->request->getParam('q', '');
        $sortBy = (string) $this->request->getParam('sort', 'name_asc');
        $rootId = (string) $this->request->getParam('rootId', '');

        try {
            $data = $this->catalogService->searchVideos(
                $userId,
                $query,
                $sortBy,
                $rootId !== '' ? $rootId : null
            );
            return new DataResponse($data);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($this->l10n->t($e->getMessage()), Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function serveSubtitle(string $rootId): Response
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $path = (string) $this->request->getParam('path', '');

        try {
            $effectiveRoots = $this->folderConfigService->getEffectiveRoots($userId);
            $vttContent = $this->subtitleService->getWebVttForPath(
                $userId,
                $rootId,
                $path,
                $effectiveRoots,
                $this->safeNodeResolver
            );

            return new class($vttContent) extends Response implements ICallbackResponse {
                public function __construct(private string $body)
                {
                    $this->setStatus(Http::STATUS_OK);
                    $this->addHeader('Content-Type', 'text/vtt; charset=utf-8');
                    $this->addHeader('Cache-Control', 'no-store');
                }

                public function render(): string
                {
                    return $this->body;
                }

                public function callback(IOutput $output): void
                {
                    $output->setOutput($this->body);
                }
            };
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
