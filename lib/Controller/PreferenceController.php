<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\Controller;

use OCA\NextcloudVideoPlayer\Service\PreferenceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class PreferenceController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private PreferenceService $preferenceService,
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
    public function get(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        return new DataResponse([
            'preferences' => $this->preferenceService->getPreferences($userId),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function update(): DataResponse
    {
        $userId = $this->requireAuthenticatedUserId();
        if ($userId instanceof DataResponse) {
            return $userId;
        }

        $params = $this->request->getParams();
        $updated = $this->preferenceService->updatePreferences($userId, $params);

        return new DataResponse([
            'preferences' => $updated,
        ]);
    }

    private function requireAuthenticatedUserId(): string|DataResponse
    {
        if (!$this->userSession->isLoggedIn() || $this->userSession->getUser() === null) {
            return new DataResponse([
                'error' => [
                    'message' => $this->l10n->t('Authentication required'),
                ],
            ], Http::STATUS_UNAUTHORIZED);
        }

        return $this->userSession->getUser()->getUID();
    }
}
