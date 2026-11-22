<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Controller;

use InvalidArgumentException;
use OCA\NextcloudVideoPlayer\Service\ConcurrentSessionLimitException;
use OCA\NextcloudVideoPlayer\Service\PlaybackSessionService;
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

class SessionController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private PlaybackSessionService $sessionService,
        private IUserSession $userSession,
        private IL10N $l10n
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function start(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $rootId = (string) $this->request->getParam('rootId', '');
        $path = (string) $this->request->getParam('path', '');
        $streamMode = (string) $this->request->getParam('streamMode', 'direct');
        $userAgent = $this->request->getHeader('User-Agent');

        try {
            $session = $this->sessionService->startSession(
                $userId,
                $rootId,
                $path,
                $streamMode,
                $userAgent !== '' ? $userAgent : null
            );
            return new DataResponse($session, Http::STATUS_CREATED);
        } catch (ConcurrentSessionLimitException $e) {
            return new DataResponse([
                'error' => [
                    'message' => $this->l10n->t($e->getMessage()),
                    'maxConcurrentSessions' => $e->getMaxConcurrentSessions(),
                    'activeSessions' => $e->getActiveSessions(),
                ],
            ], Http::STATUS_TOO_MANY_REQUESTS);
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
    public function listActive(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        return new DataResponse([
            'sessions' => $this->sessionService->listActiveSessions($userId),
            'maxConcurrentSessions' => $this->sessionService->getMaxConcurrentSessions(),
            'sessionTtlSeconds' => $this->sessionService->getSessionTtlSeconds(),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function heartbeat(string $token): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $position = $this->request->getParam('currentTime');
        $duration = $this->request->getParam('duration');
        $status = (string) $this->request->getParam('status', 'active');
        $completed = filter_var($this->request->getParam('completed', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $this->sessionService->heartbeat(
                $userId,
                $token,
                is_numeric($position) ? (float) $position : null,
                is_numeric($duration) ? (float) $duration : null,
                $status,
                $completed
            );
            return new DataResponse($result);
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
    public function close(string $token): DataResponse
    {
        return $this->handleClose($token);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function closeBeacon(string $token): DataResponse
    {
        return $this->handleClose($token);
    }

    private function handleClose(string $token): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $position = $this->request->getParam('currentTime');
        $duration = $this->request->getParam('duration');
        $completed = filter_var($this->request->getParam('completed', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $this->sessionService->closeSession(
                $userId,
                $token,
                is_numeric($position) ? (float) $position : null,
                is_numeric($duration) ? (float) $duration : null,
                $completed
            );
            return new DataResponse($result);
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
