<?php

declare(strict_types=1);

namespace OCA\FolderVideoPlayer\Controller;

use OCA\FolderVideoPlayer\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        Util::addStyle(Application::APP_ID, 'style');
        Util::addScript(Application::APP_ID, 'main');

        $response = new TemplateResponse(Application::APP_ID, 'main', [
            'appId' => Application::APP_ID,
            'appVersion' => Application::APP_VERSION,
        ]);

        $csp = new ContentSecurityPolicy();
        $csp->addAllowedMediaDomain("'self'");
        $csp->addAllowedMediaDomain('blob:');
        $csp->addAllowedImageDomain("'self'");
        $csp->addAllowedImageDomain('data:');
        $response->setContentSecurityPolicy($csp);

        return $response;
    }
}
