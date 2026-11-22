<?php

declare(strict_types=1);

namespace OCA\NextcloudVideoPlayer\AppInfo;

use OCA\NextcloudVideoPlayer\Listener\UserLoggedOutListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\User\Events\UserLoggedOutEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'nextcloud_video_player';
    public const APP_VERSION = '0.1.0';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(
            UserLoggedOutEvent::class,
            UserLoggedOutListener::class
        );
    }

    public function boot(IBootContext $context): void
    {
        $container = $context->getServerContainer();

        /** @var INavigationManager $navigationManager */
        $navigationManager = $container->get(INavigationManager::class);
        $navigationManager->add(static function () use ($container): array {
            /** @var IURLGenerator $urlGenerator */
            $urlGenerator = $container->get(IURLGenerator::class);
            /** @var IL10N $l10n */
            $l10n = $container->get(IL10N::class);

            return [
                'id' => self::APP_ID,
                'order' => 15,
                'href' => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
                'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                'name' => $l10n->t('Video Player'),
            ];
        });
    }
}
